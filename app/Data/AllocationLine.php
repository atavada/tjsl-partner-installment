<?php

declare(strict_types=1);

namespace App\Data;

readonly class AllocationLine
{
    public function __construct(
        public string $installmentScheduleId,
        public int $principalAllocated,
        public int $interestAllocated,
        public int $adminChargeAllocated,
        public int $otherChargeAllocated,
        public int $totalAllocated,
    ) {}
}
