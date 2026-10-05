<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\AuditEvent;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');

    $this->partner = Partner::factory()->create();
    $this->agreement = Agreement::factory()->create([
        'partner_id' => $this->partner->id,
        'principal_amount' => 10_000_000,
        'total_amount' => 10_600_000,
        'lifecycle_status' => AgreementLifecycleStatus::Active,
    ]);

    $this->operator = User::factory()->operator()->create();
    $this->operator->grantPermission(Permission::AgreementView);
    $this->operator->grantPermission(Permission::DocumentUpload);

    $this->viewer = User::factory()->viewer()->create();
    $this->viewer->grantPermission(Permission::AgreementView);
});

describe('Agreement Document Upload Validation & Storage', function () {
    it('rejects non-pdf upload with 422', function () {
        $file = UploadedFile::fake()->create('contract.txt', 100, 'text/plain');

        $response = $this->actingAs($this->operator)
            ->postJson("/agreements/{$this->agreement->id}/documents", [
                'file' => $file,
                'document_type' => 'contract',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    it('rejects file larger than 10MB with 422', function () {
        // 11000 KB > 10240 KB limit
        $file = UploadedFile::fake()->create('large_contract.pdf', 11000, 'application/pdf');

        $response = $this->actingAs($this->operator)
            ->postJson("/agreements/{$this->agreement->id}/documents", [
                'file' => $file,
                'document_type' => 'contract',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    it('successfully uploads valid PDF document to private storage and computes sha256', function () {
        $content = 'SYNTHETIC_PDF_BINARY_DATA_TEST_PURPOSE';
        $file = UploadedFile::fake()->createWithContent('perjanjian_final.pdf', $content);

        $response = $this->actingAs($this->operator)
            ->postJson("/agreements/{$this->agreement->id}/documents", [
                'file' => $file,
                'document_type' => 'contract',
                'notes' => 'Berkas kontrak asli ditandatangani',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('document.file_name', 'perjanjian_final.pdf')
            ->assertJsonPath('document.checksum_sha256', hash('sha256', $content));

        $doc = AgreementDocument::where('agreement_id', $this->agreement->id)->first();
        expect($doc)->not->toBeNull();
        expect($doc->file_name)->toBe('perjanjian_final.pdf');
        expect($doc->checksum_sha256)->toBe(hash('sha256', $content));
        expect($doc->mime_type)->toBe('application/pdf');
        expect(Storage::disk('local')->exists($doc->file_path))->toBeTrue();

        // Check agreement financial attributes remain untouched
        $this->agreement->refresh();
        expect($this->agreement->principal_amount)->toBe(10_000_000);
        expect($this->agreement->total_amount)->toBe(10_600_000);
        expect($this->agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Active);
    });

    it('denies document upload to unauthorized user', function () {
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->viewer)
            ->postJson("/agreements/{$this->agreement->id}/documents", [
                'file' => $file,
            ]);

        $response->assertForbidden();
    });
});

describe('Agreement Document Download, Audit & Signed URLs', function () {
    beforeEach(function () {
        $content = 'SYNTHETIC_CONTRACT_STREAM_CONTENT';
        $storagePath = "agreements/{$this->agreement->id}/test_doc.pdf";
        Storage::disk('local')->put($storagePath, $content);

        $this->document = AgreementDocument::create([
            'agreement_id' => $this->agreement->id,
            'file_path' => $storagePath,
            'file_name' => 'test_doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($content),
            'checksum_sha256' => hash('sha256', $content),
            'document_type' => 'contract',
            'document_version' => 1,
            'version' => 1,
        ]);
    });

    it('download stream emits document_downloaded audit event', function () {
        $response = $this->actingAs($this->operator)
            ->get("/agreements/{$this->agreement->id}/documents/{$this->document->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $audit = AuditEvent::where('action', 'document_downloaded')
            ->where('target_id', $this->document->id)
            ->where('actor_id', $this->operator->id)
            ->first();

        expect($audit)->not->toBeNull();
        expect($audit->actor_type)->toBe('user');
        expect($audit->delta['file_name'])->toBe('test_doc.pdf');
    });

    it('denies download to viewer role without document.download permission per DEC-004', function () {
        $response = $this->actingAs($this->viewer)
            ->get("/agreements/{$this->agreement->id}/documents/{$this->document->id}/download");

        $response->assertForbidden();
    });

    it('permits download to viewer role when explicitly granted document.download permission', function () {
        $this->viewer->grantPermission(Permission::DocumentDownload);

        $response = $this->actingAs($this->viewer)
            ->get("/agreements/{$this->agreement->id}/documents/{$this->document->id}/download");

        $response->assertOk();
    });

    it('generates temporary signed URL and allows verified download', function () {
        $response = $this->actingAs($this->operator)
            ->getJson("/agreements/{$this->agreement->id}/documents/{$this->document->id}/signed-url");

        $response->assertOk();
        $signedUrl = $response->json('signed_url');
        expect($signedUrl)->toBeString();
        expect($signedUrl)->toContain('signed-download');

        // Access via signed URL
        $downloadResponse = $this->actingAs($this->operator)->get($signedUrl);
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('Content-Type', 'application/pdf');
    });

    it('rejects tampered signed URL with 403', function () {
        $signedUrl = URL::temporarySignedRoute(
            'agreements.documents.signed-download',
            now()->addMinutes(30),
            ['agreement' => $this->agreement->id, 'document' => $this->document->id]
        );

        $tamperedUrl = $signedUrl.'&extra=tamper';
        $response = $this->get($tamperedUrl);
        $response->assertForbidden();
    });

    it('returns 404 if document does not belong to agreement', function () {
        $otherAgreement = Agreement::factory()->create();

        $response = $this->actingAs($this->operator)
            ->get("/agreements/{$otherAgreement->id}/documents/{$this->document->id}/download");

        $response->assertNotFound();
    });
});
