<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Models\Agreement;
use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PartnerSearchService
{
    /**
     * Search partners across NO ID, alias/name, agreement number, or VA.
     */
    public function search(?string $query = null, ?string $type = null, int $perPage = 15, ?User $user = null): LengthAwarePaginator
    {
        $builder = Partner::query()
            ->with(['aliases', 'virtualAccounts'])
            ->withCount('agreements');

        $trimmedQuery = $query !== null ? trim($query) : '';

        if ($trimmedQuery === '' || $type === null || $type === '') {
            return $builder->orderBy('created_at', 'desc')->paginate($perPage);
        }

        $builder = match ($type) {
            'no_id' => $this->applyNoIdFilter($builder, $trimmedQuery),
            'name' => $this->applyNameFilter($builder, $trimmedQuery),
            'agreement' => $this->applyAgreementFilter($builder, $trimmedQuery),
            'va' => $this->applyVaFilter($builder, $trimmedQuery, $user),
            default => $builder->whereRaw('1 = 0'),
        };

        return $builder->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Exact match on partner_no_id_normalized, preserving leading zeros.
     */
    private function applyNoIdFilter(Builder $builder, string $query): Builder
    {
        $normalized = Partner::normalizePartnerNoId($query);

        if ($normalized === null) {
            return $builder->whereRaw('1 = 0');
        }

        return $builder->where('partner_no_id_normalized', $normalized);
    }

    /**
     * Match on alias name_normalized or partner name.
     * Distinct partners with same name remain separate records (no auto-merge).
     */
    private function applyNameFilter(Builder $builder, string $query): Builder
    {
        $normalized = PartnerAlias::normalizeName($query);

        if ($normalized === '') {
            return $builder->whereRaw('1 = 0');
        }

        $escapedNormalized = addcslashes($normalized, '%_\\');
        $escapedQuery = addcslashes($query, '%_\\');

        return $builder->where(function (Builder $subQuery) use ($escapedNormalized, $escapedQuery) {
            $subQuery->whereHas('aliases', function (Builder $aliasQuery) use ($escapedNormalized) {
                $aliasQuery->where('name_normalized', 'like', "%{$escapedNormalized}%");
            })->orWhere('name', 'like', "%{$escapedQuery}%");
        });
    }

    /**
     * Search by agreement number.
     * Per DEC-001, agreement number groups partners; returns all candidates in group.
     */
    private function applyAgreementFilter(Builder $builder, string $query): Builder
    {
        $normalized = Agreement::normalizeAgreementNumber($query);

        if ($normalized === null) {
            return $builder->whereRaw('1 = 0');
        }

        return $builder->whereHas('agreements', function (Builder $agreementQuery) use ($normalized) {
            $agreementQuery->where('agreement_number_normalized', $normalized);
        });
    }

    /**
     * Exact match on VA number preserving leading zeros.
     */
    private function applyVaFilter(Builder $builder, string $query, ?User $user = null): Builder
    {
        $actor = $user ?? auth()->user();

        if ($actor === null || ! $actor->can(Permission::VaReveal->value)) {
            return $builder->whereRaw('1 = 0');
        }

        $normalized = VirtualAccount::normalizeVaNumber($query);

        if ($normalized === '') {
            return $builder->whereRaw('1 = 0');
        }

        return $builder->whereHas('virtualAccounts', function (Builder $vaQuery) use ($normalized) {
            $vaQuery->where('va_number_normalized', $normalized);
        });
    }
}
