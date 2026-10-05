<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadAgreementDocumentRequest;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use App\Services\AgreementDocumentService;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgreementDocumentController extends Controller
{
    /**
     * Store an uploaded agreement document (PDF).
     */
    public function store(
        UploadAgreementDocumentRequest $request,
        Agreement $agreement,
        AgreementDocumentService $documentService,
        AuditService $auditService
    ): JsonResponse|RedirectResponse {
        $file = $request->file('file');

        $document = $documentService->store(
            agreement: $agreement,
            file: $file,
            originalFileName: $file->getClientOriginalName(),
            mimeType: 'application/pdf',
            documentType: (string) ($request->input('document_type') ?: 'contract'),
            notes: $request->input('notes'),
            uploader: $request->user(),
        );

        $auditService->logModelCreated($document, $request->user(), 'Dokumen perjanjian diunggah');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Dokumen berhasil diunggah.',
                'document' => [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'file_size_bytes' => $document->file_size_bytes,
                    'checksum_sha256' => $document->checksum_sha256,
                    'document_version' => $document->document_version,
                ],
            ], 201);
        }

        return back()->with('success', 'Dokumen perjanjian berhasil diunggah.');
    }

    /**
     * Download an agreement document with audit logging.
     */
    public function download(
        Request $request,
        Agreement $agreement,
        AgreementDocument $document,
        AgreementDocumentService $documentService
    ): StreamedResponse {
        abort_unless($document->agreement_id === $agreement->id, 404, 'Dokumen tidak ditemukan untuk perjanjian ini.');

        Gate::authorize('downloadDocument', [$agreement, $document]);

        $content = $documentService->download($document, $request->user(), 'document_downloaded');

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $document->file_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
            ]
        );
    }

    /**
     * Generate an expiring signed URL for secure document access.
     */
    public function signedUrl(
        Request $request,
        Agreement $agreement,
        AgreementDocument $document
    ): JsonResponse {
        abort_unless($document->agreement_id === $agreement->id, 404, 'Dokumen tidak ditemukan untuk perjanjian ini.');

        Gate::authorize('downloadDocument', [$agreement, $document]);

        $signedUrl = URL::temporarySignedRoute(
            'agreements.documents.signed-download',
            now()->addMinutes(30),
            ['agreement' => $agreement->id, 'document' => $document->id]
        );

        return response()->json([
            'signed_url' => $signedUrl,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ]);
    }

    /**
     * Stream document download via verified signed route.
     */
    public function signedDownload(
        Request $request,
        Agreement $agreement,
        AgreementDocument $document,
        AgreementDocumentService $documentService
    ): StreamedResponse {
        abort_unless($document->agreement_id === $agreement->id, 404, 'Dokumen tidak ditemukan untuk perjanjian ini.');

        $content = $documentService->download($document, $request->user(), 'document_downloaded');

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $document->file_name,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$document->file_name.'"',
            ]
        );
    }
}
