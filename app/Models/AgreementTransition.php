<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AgreementTransitionType;
use Database\Factories\AgreementTransitionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgreementTransition extends Model
{
    /** @use HasFactory<AgreementTransitionFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'predecessor_id',
        'successor_id',
        'transition_type',
        'effective_date',
        'reason',
        'approved_principal_amount',
        'approved_interest_amount',
        'approved_admin_charge_amount',
        'approved_by_id',
        'approved_at',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'transition_type' => AgreementTransitionType::class,
            'effective_date' => 'date',
            'approved_principal_amount' => 'integer',
            'approved_interest_amount' => 'integer',
            'approved_admin_charge_amount' => 'integer',
            'approved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Agreement, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'predecessor_id');
    }

    /** @return BelongsTo<Agreement, $this> */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Agreement::class, 'successor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /** @return HasMany<AgreementDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AgreementDocument::class, 'transition_id');
    }
}
