<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementSigningStatus;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgreementDocumentService
{
    private const DISK = 'local';

    /**
     * Store file to private storage, calculate checksum, create AgreementDocument.
     * Upload is not proof of signature; does not change agreement lifecycle or balance.
     */
    public function store(
        Agreement $agreement,
        UploadedFile|string $file,
        string $originalFileName,
        string $mimeType,
        string $documentType = 'contract',
        ?string $notes = null,
        ?User $uploader = null
    ): AgreementDocument {
        $content = $file instanceof UploadedFile ? $file->get() : $file;
        $checksum = hash('sha256', $content);
        $fileSize = strlen($content);

        $storagePath = sprintf('agreements/%s/%s_%s', $agreement->id, (string) Str::uuid(), $originalFileName);
        Storage::disk(self::DISK)->put($storagePath, $content);

        return AgreementDocument::create([
            'agreement_id' => $agreement->id,
            'file_path' => $storagePath,
            'file_name' => $originalFileName,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksum,
            'document_type' => $documentType,
            'document_version' => 1,
            'uploaded_by_id' => $uploader?->id,
            'signing_status' => AgreementSigningStatus::NotPrepared,
            'signature_summary' => SignatureSummary::Unknown,
            'notes' => $notes,
            'version' => 1,
        ]);
    }

    /**
     * Verify document checksum against private storage file.
     */
    public function verifyChecksum(AgreementDocument $document): bool
    {
        if (! Storage::disk(self::DISK)->exists($document->file_path)) {
            return false;
        }

        $content = (string) Storage::disk(self::DISK)->get($document->file_path);
        $actualChecksum = hash('sha256', $content);

        return hash_equals($document->checksum_sha256, $actualChecksum);
    }

    /**
     * Retrieve document content with audit logging (PRD §4, FR-06).
     */
    public function download(AgreementDocument $document, User $actor): string
    {
        if (app()->bound(AuditService::class)) {
            app(AuditService::class)->logDocumentAccess($document, $actor, 'download');
        }

        return (string) Storage::disk(self::DISK)->get($document->file_path);
    }
}
