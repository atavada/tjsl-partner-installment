<?php

declare(strict_types=1);

use App\Enums\CollectibilityStatus;

describe('CollectibilityStatus enum cases and UI labels (DEC-007, Collectibility Label v1)', function () {
    it('defines all six confirmed collectibility cases and Indonesian UI labels', function () {
        expect(CollectibilityStatus::Lancar->value)->toBe('lancar')
            ->and(CollectibilityStatus::Lancar->label())->toBe('Lancar');

        expect(CollectibilityStatus::KurangLancar->value)->toBe('kurang_lancar')
            ->and(CollectibilityStatus::KurangLancar->label())->toBe('Kurang Lancar');

        expect(CollectibilityStatus::Diragukan->value)->toBe('diragukan')
            ->and(CollectibilityStatus::Diragukan->label())->toBe('Diragukan');

        expect(CollectibilityStatus::Bermasalah->value)->toBe('bermasalah')
            ->and(CollectibilityStatus::Bermasalah->label())->toBe('Bermasalah');

        expect(CollectibilityStatus::Lunas->value)->toBe('lunas')
            ->and(CollectibilityStatus::Lunas->label())->toBe('LUNAS');

        expect(CollectibilityStatus::Unknown->value)->toBe('unknown')
            ->and(CollectibilityStatus::Unknown->label())->toBe('Tidak Diketahui');
    });

    it('provides data-driven month bands for active rule version', function () {
        $bands = CollectibilityStatus::monthBand();

        expect($bands)->toBeArray()
            ->and($bands)->toHaveCount(4)
            ->and($bands[0]['status'])->toBe(CollectibilityStatus::Lancar)
            ->and($bands[0]['min_late_months'])->toBe(0)
            ->and($bands[0]['max_late_months'])->toBe(1)
            ->and($bands[1]['status'])->toBe(CollectibilityStatus::KurangLancar)
            ->and($bands[1]['min_late_months'])->toBe(2)
            ->and($bands[1]['max_late_months'])->toBe(6)
            ->and($bands[2]['status'])->toBe(CollectibilityStatus::Diragukan)
            ->and($bands[2]['min_late_months'])->toBe(7)
            ->and($bands[2]['max_late_months'])->toBe(9)
            ->and($bands[3]['status'])->toBe(CollectibilityStatus::Bermasalah)
            ->and($bands[3]['min_late_months'])->toBe(10)
            ->and($bands[3]['max_late_months'])->toBeNull();
    });

    it('throws InvalidArgumentException for unknown rule version', function () {
        expect(fn () => CollectibilityStatus::monthBand('invalid_version'))
            ->toThrow(InvalidArgumentException::class, 'Unknown collectibility rule version: invalid_version');
    });
});

describe('CollectibilityStatus::fromLateMonths (DEC-007 FINAL month bands)', function () {
    it('maps 0 late months to Lancar', function () {
        expect(CollectibilityStatus::fromLateMonths(0))->toBe(CollectibilityStatus::Lancar);
    });

    it('maps 1 late month to Lancar', function () {
        expect(CollectibilityStatus::fromLateMonths(1))->toBe(CollectibilityStatus::Lancar);
    });

    it('maps 2 late months to Kurang Lancar', function () {
        expect(CollectibilityStatus::fromLateMonths(2))->toBe(CollectibilityStatus::KurangLancar);
    });

    it('maps 6 late months to Kurang Lancar', function () {
        expect(CollectibilityStatus::fromLateMonths(6))->toBe(CollectibilityStatus::KurangLancar);
    });

    it('maps 7 late months to Diragukan', function () {
        expect(CollectibilityStatus::fromLateMonths(7))->toBe(CollectibilityStatus::Diragukan);
    });

    it('maps 9 late months to Diragukan', function () {
        expect(CollectibilityStatus::fromLateMonths(9))->toBe(CollectibilityStatus::Diragukan);
    });

    it('maps 10 late months to Bermasalah', function () {
        expect(CollectibilityStatus::fromLateMonths(10))->toBe(CollectibilityStatus::Bermasalah);
    });

    it('maps large late months (>9) to Bermasalah', function () {
        expect(CollectibilityStatus::fromLateMonths(24))->toBe(CollectibilityStatus::Bermasalah);
    });

    it('clamps negative late months to 0 (Lancar)', function () {
        expect(CollectibilityStatus::fromLateMonths(-1))->toBe(CollectibilityStatus::Lancar);
    });
});

describe('CollectibilityStatus::fromBalance (docs/formula-specification.md §9.4)', function () {
    it('returns Lunas when remaining balance is 0 and data is verified', function () {
        expect(CollectibilityStatus::fromBalance(0, true, 5))->toBe(CollectibilityStatus::Lunas);
    });

    it('returns Unknown when data is not verified regardless of balance or late months', function () {
        expect(CollectibilityStatus::fromBalance(100000, false, 0))->toBe(CollectibilityStatus::Unknown);
    });

    it('guards against legacy bug: unverified data NEVER becomes Lunas even with zero balance', function () {
        expect(CollectibilityStatus::fromBalance(0, false, 0))->toBe(CollectibilityStatus::Unknown)
            ->and(CollectibilityStatus::fromBalance(0, false, 5))->toBe(CollectibilityStatus::Unknown);
    });

    it('delegates to fromLateMonths when data is verified and balance is positive', function () {
        expect(CollectibilityStatus::fromBalance(100000, true, 3))->toBe(CollectibilityStatus::KurangLancar);
        expect(CollectibilityStatus::fromBalance(5000000, true, 0))->toBe(CollectibilityStatus::Lancar);
        expect(CollectibilityStatus::fromBalance(5000000, true, 8))->toBe(CollectibilityStatus::Diragukan);
        expect(CollectibilityStatus::fromBalance(5000000, true, 12))->toBe(CollectibilityStatus::Bermasalah);
    });
});
