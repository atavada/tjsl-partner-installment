<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Partner;
use App\Models\PartnerAlias;
use App\Models\VirtualAccount;
use App\Services\MaskingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PartnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MaskingService $maskingService */
        $maskingService = app(MaskingService::class);
        $viewer = $request->user();

        $maskedPartner = $maskingService->maskPartner($this->resource, $viewer);

        $verificationLabel = match ($this->verification_state) {
            'verified' => 'Terverifikasi',
            'pending' => 'Menunggu Verifikasi',
            default => 'Belum Terverifikasi',
        };

        return array_merge($maskedPartner, [
            'verification_badge_label' => $verificationLabel,
            'aliases' => $this->relationLoaded('aliases')
                ? $this->aliases->map(fn (PartnerAlias $alias) => [
                    'id' => $alias->id,
                    'name_raw' => $alias->name_raw,
                    'name_normalized' => $alias->name_normalized,
                    'state' => $alias->state,
                ])->values()->all()
                : [],
            'virtual_accounts' => $this->relationLoaded('virtualAccounts')
                ? $this->virtualAccounts->map(fn (VirtualAccount $va) => $maskingService->maskVirtualAccount($va, $viewer))->values()->all()
                : [],
            'agreements_count' => $this->agreements_count ?? ($this->relationLoaded('agreements') ? $this->agreements->count() : $this->agreements()->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
