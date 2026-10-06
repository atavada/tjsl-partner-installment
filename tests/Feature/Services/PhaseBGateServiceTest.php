<?php

declare(strict_types=1);

use App\Exceptions\NotApprovedException;
use App\Services\PhaseBGateService;

describe('PhaseBGateService (PRD §8, §9, Phase B Gate Enforcement)', function () {
    beforeEach(function () {
        $this->service = new PhaseBGateService;
        config(['finance.phase_b_approved' => false]);
    });

    it('allows synthetic data ingestion in prototype mode', function () {
        expect(fn () => $this->service->assertIngestionAllowed(true, 'synthetic_test.xlsx'))
            ->not->toThrow(NotApprovedException::class);
    });

    it('throws NotApprovedException for production/live workbook ingestion when Phase B is unapproved', function () {
        expect(fn () => $this->service->assertIngestionAllowed(false, 'PROD_BANK_MUTASI_2026.csv'))
            ->toThrow(NotApprovedException::class, 'is blocked pending Phase B gate approval per PRD §9.');
    });

    it('allows live ingestion when Phase B approval is explicitly enabled in config', function () {
        config(['finance.phase_b_approved' => true]);

        expect(fn () => $this->service->assertIngestionAllowed(false, 'PROD_BANK_MUTASI_2026.csv'))
            ->not->toThrow(NotApprovedException::class);
    });
});
