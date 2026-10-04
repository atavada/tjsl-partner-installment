<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_installment_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_allocation_id')->constrained('payment_allocations')->cascadeOnDelete();
            $table->foreignUuid('installment_schedule_id')->constrained('installment_schedules')->cascadeOnDelete();

            // Allocation financial components to this installment (integer IDR)
            $table->unsignedBigInteger('principal_amount')->default(0);
            $table->unsignedBigInteger('interest_amount')->default(0);
            $table->unsignedBigInteger('admin_charge_amount')->default(0);
            $table->unsignedBigInteger('other_charge_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            $table->timestamps();

            // Indexes for fast lookup
            $table->index('payment_allocation_id');
            $table->index('installment_schedule_id');
            $table->index(['payment_allocation_id', 'installment_schedule_id'], 'alloc_inst_lines_alloc_inst_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_installment_lines');
    }
};
