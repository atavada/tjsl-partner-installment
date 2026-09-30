<?php

declare(strict_types=1);

use App\Enums\AgreementLifecycleStatus;
use App\Enums\AgreementSigningStatus;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Services\AgreementDocumentService;
use Illuminate\Support\Facades\Storage;

describe('AgreementDocumentService private storage and checksum verification', function () {
    beforeEach(function () {
        Storage::fake('local');
    });

    it('stores document to private disk with calculated sha256 checksum', function () {
        $service = new AgreementDocumentService;
        $agreement = Agreement::factory()->create();

        $content = 'SYNTHETIC_CONTRACT_CONTENT_FOR_TESTING_PURPOSES';
        $fileName = 'perjanjian_kredit.pdf';
        $mimeType = 'application/pdf';

        $document = $service->store(
            agreement: $agreement,
            file: $content,
            originalFileName: $fileName,
            mimeType: $mimeType,
            documentType: 'contract',
            notes: 'Dokumen perjanjian awal'
        );

        expect($document)->toBeInstanceOf(AgreementDocument::class);
        expect($document->agreement_id)->toBe($agreement->id);
        expect($document->file_name)->toBe($fileName);
        expect($document->checksum_sha256)->toBe(hash('sha256', $content));
        expect($document->file_size_bytes)->toBe(strlen($content));

        // Stored on private local disk
        expect(Storage::disk('local')->exists($document->file_path))->toBeTrue();
        expect(Storage::disk('local')->get($document->file_path))->toBe($content);
    });

    it('verifies valid checksum and detects tampering or missing file', function () {
        $service = new AgreementDocumentService;
        $agreement = Agreement::factory()->create();

        $content = 'AUTHENTIC_SYNTHETIC_DATA_PAYLOAD';
        $document = $service->store(
            agreement: $agreement,
            file: $content,
            originalFileName: 'contract.pdf',
            mimeType: 'application/pdf'
        );

        // Verification passes for authentic file
        expect($service->verifyChecksum($document))->toBeTrue();

        // Tamper with file content
        Storage::disk('local')->put($document->file_path, 'TAMPERED_DATA');
        expect($service->verifyChecksum($document))->toBeFalse();

        // Missing file
        Storage::disk('local')->delete($document->file_path);
        expect($service->verifyChecksum($document))->toBeFalse();
    });

    it('document upload does not alter agreement balances or statuses', function () {
        $service = new AgreementDocumentService;
        $agreement = Agreement::factory()->create([
            'principal_amount' => 20_000_000,
            'total_amount' => 21_200_000,
            'lifecycle_status' => AgreementLifecycleStatus::Draft,
            'signing_status' => AgreementSigningStatus::NotPrepared,
        ]);

        $service->store(
            agreement: $agreement,
            file: 'SOME_SYNTHETIC_PDF_BYTES',
            originalFileName: 'draft_contract.pdf',
            mimeType: 'application/pdf'
        );

        $agreement->refresh();

        expect($agreement->principal_amount)->toBe(20_000_000);
        expect($agreement->total_amount)->toBe(21_200_000);
        expect($agreement->lifecycle_status)->toBe(AgreementLifecycleStatus::Draft);
        expect($agreement->signing_status)->toBe(AgreementSigningStatus::NotPrepared);
    });
});
