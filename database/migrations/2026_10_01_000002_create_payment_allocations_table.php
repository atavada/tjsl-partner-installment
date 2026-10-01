<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignUuid('agreement_id')->constrained('agreements')->cascadeOnDelete();

            // Allocation financial components: integer IDR (PRD §2, §4)
            $table->unsignedBigInteger('principal_amount')->default(0);
            $table->unsignedBigInteger('interest_amount')->default(0);
            $table->unsignedBigInteger('admin_charge_amount')->default(0);
            $table->unsignedBigInteger('other_charge_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            // Effective date & accounting period (DEC-010: period derivation OPEN)
            $table->date('effective_date');
            $table->string('period', 7)->nullable();

            // Payment state: draft, submitted, posted, reversed (PRD §5 FR-03)
            $table->string('state', 30)->default('draft');
            $table->text('evidence')->nullable();
            $table->uuid('idempotency_key')->unique();

            // Authorization & double-posting guard (PRD §4 invariant 5)
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_source', 255)->nullable();

            // Compensating entries: no physical delete (PRD §4 invariant 4)
            $table->foreignUuid('reversal_of_id')->nullable()->constrained('payment_allocations')->nullOnDelete();
            $table->text('reason')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Indexes
            $table->index(['bank_transaction_id', 'agreement_id']);
            $table->index('agreement_id');
            $table->index('state');
            $table->index('approved_source');
            $table->index('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
