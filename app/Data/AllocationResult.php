<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Support\Collection;

readonly class AllocationResult
{
    /**
     * @param  Collection<int, AllocationLine>  $lines
     */
    public function __construct(
        public Collection $lines,
        public int $totalAllocated,
        public int $excessAmount,
        public int $principalAllocated = 0,
        public int $interestAllocated = 0,
        public int $adminChargeAllocated = 0,
        public int $otherChargeAllocated = 0,
    ) {}
}
