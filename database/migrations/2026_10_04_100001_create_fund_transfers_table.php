<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates fund_transfers table for tracking cross-partner reallocation
     * of excess/unallocated fund lots per DEC-006 and formula-specification.md §7.
     */
    public function up(): void
    {
        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_lot_id')->constrained('fund_lots')->restrictOnDelete();
            $table->foreignUuid('target_partner_id')->constrained('partners')->restrictOnDelete();
            $table->foreignUuid('target_agreement_id')->constrained('agreements')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('reason');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->date('effective_date');
            $table->foreignUuid('linked_allocation_id')->nullable()->constrained('payment_allocations')->nullOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('source_lot_id');
            $table->index('target_partner_id');
            $table->index('target_agreement_id');
            $table->index('linked_allocation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
    }
};
