<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgreementSigningStatus;
use App\Enums\SignatureSummary;
use App\Models\Agreement;
use App\Models\AgreementDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementDocument>
 */
class AgreementDocumentFactory extends Factory
{
    protected $model = AgreementDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'transition_id' => null,
            'file_path' => 'agreements/synthetic/contract.pdf',
            'file_name' => 'synthetic_perjanjian.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 102400,
            'checksum_sha256' => hash('sha256', 'synthetic_contract_file_content'),
            'document_type' => 'contract',
            'document_version' => 1,
            'uploaded_by_id' => null,
            'signing_status' => AgreementSigningStatus::NotPrepared,
            'signature_summary' => SignatureSummary::Unknown,
            'notes' => null,
            'version' => 1,
        ];
    }
}
