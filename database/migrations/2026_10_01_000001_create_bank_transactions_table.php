<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Bank reference and provider namespace (DEC-011: uniqueness scope OPEN)
            $table->string('reference', 255)->nullable();
            $table->string('reference_normalized', 255)->nullable();
            $table->string('reference_namespace', 100)->nullable();

            // Transaction timestamp & timezone (DEC-010: receipt timezone)
            $table->timestamp('transaction_datetime');
            $table->string('timezone', 50)->default('Asia/Jakarta');

            // Financial amount: integer IDR, non-negative raw deposit (PRD §2, §4)
            $table->unsignedBigInteger('amount');

            // Payer information: sensitive fields masked by default (DEC-004)
            $table->string('payer_name', 255)->nullable();
            $table->string('payer_va', 50)->nullable();

            // Ingestion source and duplicate prevention (PRD §4, §5 FR-03)
            $table->string('source', 255)->nullable();
            $table->string('source_row_identifier', 255)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->uuid('idempotency_key')->unique();

            // Lifecycle state: draft, submitted, posted, reversed (PRD §5 FR-03)
            $table->string('state', 30)->default('draft');

            // Period derivation: receipt_month YYYY-MM (DEC-010)
            $table->string('receipt_month', 7)->nullable();

            // Provenance and audit metadata
            $table->string('provenance', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Indexes
            $table->index('fingerprint');
            $table->index(['reference_namespace', 'reference_normalized']);
            $table->index(['source', 'reference']);
            $table->index('state');
            $table->index('receipt_month');
            $table->index('payer_va');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
