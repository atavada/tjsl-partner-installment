<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reconciliation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('case_number', 50)->unique();
            $table->string('case_type', 50);
            $table->foreignUuid('source_row_id')->nullable()->constrained('source_rows')->nullOnDelete();
            $table->foreignUuid('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->foreignUuid('agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->string('discrepancy_type', 50);
            $table->string('status', 50)->default('unreviewed');
            $table->json('candidate_matches')->nullable();
            $table->text('evidence')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index('status');
            $table->index('discrepancy_type');
            $table->index('case_type');
            $table->index(['status', 'discrepancy_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_cases');
    }
};
