<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agreement_id')->constrained('agreements')->cascadeOnDelete();

            // Adjustment type: opening_balance, correction, write_off, transfer, reversal
            $table->string('adjustment_type', 50);

            // Adjustment components: integer IDR, SIGNED (corrections/reversals can be negative)
            $table->bigInteger('principal_amount')->default(0);
            $table->bigInteger('interest_amount')->default(0);
            $table->bigInteger('admin_charge_amount')->default(0);
            $table->bigInteger('other_charge_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);

            // Effective date
            $table->date('effective_date');

            // Audit & authorization
            $table->text('reason')->nullable();
            $table->text('evidence')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('state', 30)->default('draft');

            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Compensating entries: no physical delete (PRD §4 invariant 4)
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('receivable_adjustments')->nullOnDelete();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Indexes
            $table->index('agreement_id');
            $table->index('adjustment_type');
            $table->index('state');
            $table->index('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_adjustments');
    }
};
