<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementSigningStatus;
use App\Enums\SignatureSummary;
use Database\Factories\AgreementDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgreementDocument extends Model
{
    /** @use HasFactory<AgreementDocumentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'agreement_id',
        'transition_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'document_type',
        'document_version',
        'uploaded_by_id',
        'signing_status',
        'signature_summary',
        'notes',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'document_version' => 'integer',
            'signing_status' => AgreementSigningStatus::class,
            'signature_summary' => SignatureSummary::class,
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Agreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /** @return BelongsTo<AgreementTransition, $this> */
    public function transition(): BelongsTo
    {
        return $this->belongsTo(AgreementTransition::class, 'transition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
