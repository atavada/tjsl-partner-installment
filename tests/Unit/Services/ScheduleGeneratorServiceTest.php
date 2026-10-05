<?php

declare(strict_types=1);

use App\Services\ScheduleGeneratorService;

describe('ScheduleGeneratorService::edate (Excel-compatible EDATE calendar math)', function () {
    it('returns exact anchor date when months is 0', function () {
        $result = ScheduleGeneratorService::edate('2026-01-31', 0);

        expect($result->toDateString())->toBe('2026-01-31');
    });

    it('handles month-end clamping without month-end drift across 12 months', function () {
        $expected = [
            0 => '2026-01-31',
            1 => '2026-02-28',
            2 => '2026-03-31',
            3 => '2026-04-30',
            4 => '2026-05-31',
            5 => '2026-06-30',
            6 => '2026-07-31',
            7 => '2026-08-31',
            8 => '2026-09-30',
            9 => '2026-10-31',
            10 => '2026-11-30',
            11 => '2026-12-31',
            12 => '2027-01-31',
        ];

        foreach ($expected as $months => $dateString) {
            $result = ScheduleGeneratorService::edate('2026-01-31', $months);
            expect($result->toDateString())->toBe($dateString);
        }
    });

    it('correctly uses February 29 during leap years', function () {
        $leapResult = ScheduleGeneratorService::edate('2024-01-31', 1);
        expect($leapResult->toDateString())->toBe('2024-02-29');

        $nonLeapResult = ScheduleGeneratorService::edate('2025-01-31', 1);
        expect($nonLeapResult->toDateString())->toBe('2025-02-28');
    });

    it('preserves exact mid-month day across months', function () {
        $result1 = ScheduleGeneratorService::edate('2026-02-15', 1);
        expect($result1->toDateString())->toBe('2026-03-15');

        $result6 = ScheduleGeneratorService::edate('2026-02-15', 6);
        expect($result6->toDateString())->toBe('2026-08-15');
    });
});

describe('ScheduleGeneratorService::calculateSchedules (Integer division & remainder placement)', function () {
    it('creates 11 installments of 1,666,666 and 1 installment of 1,666,674 for 20M / 12 (0 IDR difference)', function () {
        $service = new ScheduleGeneratorService;
        $schedules = $service->calculateSchedules(
            principal: 20_000_000,
            interest: 1_200_000,
            adminCharge: 100_000,
            otherCharge: 0,
            tenor: 12,
            firstDueDate: '2026-02-01'
        );

        expect($schedules)->toHaveCount(12);

        // Principal: 20,000,000 / 12 = 1,666,666 with remainder 8 on month 12
        for ($i = 0; $i < 11; $i++) {
            expect($schedules[$i]['principal_due'])->toBe(1_666_666)
                ->and($schedules[$i]['interest_due'])->toBe(100_000)
                ->and($schedules[$i]['admin_charge_due'])->toBe(8_333)
                ->and($schedules[$i]['other_charge_due'])->toBe(0)
                ->and($schedules[$i]['total_due'])->toBe(1_774_999);
        }

        // Final installment (month 12) receives remainder: 1,666,674 principal, 8,337 admin
        expect($schedules[11]['principal_due'])->toBe(1_666_674)
            ->and($schedules[11]['interest_due'])->toBe(100_000)
            ->and($schedules[11]['admin_charge_due'])->toBe(8_337)
            ->and($schedules[11]['other_charge_due'])->toBe(0)
            ->and($schedules[11]['total_due'])->toBe(1_775_011);

        // Total sums match exact contract down to Rp0
        $sumPrincipal = array_sum(array_column($schedules, 'principal_due'));
        $sumInterest = array_sum(array_column($schedules, 'interest_due'));
        $sumAdmin = array_sum(array_column($schedules, 'admin_charge_due'));
        $sumTotal = array_sum(array_column($schedules, 'total_due'));

        expect($sumPrincipal)->toBe(20_000_000)
            ->and($sumInterest)->toBe(1_200_000)
            ->and($sumAdmin)->toBe(100_000)
            ->and($sumTotal)->toBe(21_300_000);
    });

    it('handles tenor 1 with entire amount in single installment', function () {
        $service = new ScheduleGeneratorService;
        $schedules = $service->calculateSchedules(
            principal: 5_000_000,
            interest: 300_000,
            adminCharge: 50_000,
            otherCharge: 0,
            tenor: 1,
            firstDueDate: '2026-03-01'
        );

        expect($schedules)->toHaveCount(1)
            ->and($schedules[0]['installment_number'])->toBe(1)
            ->and($schedules[0]['principal_due'])->toBe(5_000_000)
            ->and($schedules[0]['interest_due'])->toBe(300_000)
            ->and($schedules[0]['admin_charge_due'])->toBe(50_000)
            ->and($schedules[0]['total_due'])->toBe(5_350_000);
    });

    it('rejects invalid tenor (< 1)', function () {
        $service = new ScheduleGeneratorService;

        expect(fn () => $service->calculateSchedules(10_000_000, 0, 0, 0, 0, '2026-01-01'))
            ->toThrow(InvalidArgumentException::class, 'Tenor months must be at least 1, 0 given.');
    });

    it('rejects negative financial amounts', function () {
        $service = new ScheduleGeneratorService;

        expect(fn () => $service->calculateSchedules(-1000, 0, 0, 0, 12, '2026-01-01'))
            ->toThrow(InvalidArgumentException::class, 'Financial components must be non-negative.');
    });
});
