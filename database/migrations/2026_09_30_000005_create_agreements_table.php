<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();

            // Agreement number: raw string + normalized string (DEC-001: grouping key, NOT unique)
            $table->string('agreement_number', 100);
            $table->string('agreement_number_normalized', 100);

            // Grouping metadata (DEC-001)
            $table->string('batch_year', 10)->nullable();
            $table->string('business_group', 100)->nullable();
            $table->unsignedInteger('source_row_number')->nullable();

            // Contract dates
            $table->date('application_date')->nullable();
            $table->date('contract_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('maturity_date')->nullable();

            // Financial components (integer IDR, default 0, PRD §2, non-negative)
            $table->unsignedBigInteger('principal_amount')->default(0);
            $table->unsignedBigInteger('interest_amount')->default(0);
            $table->unsignedBigInteger('admin_charge_amount')->default(0);
            $table->unsignedBigInteger('other_charge_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);

            // Dimension 1: Lifecycle status (DEC-002)
            $table->string('lifecycle_status', 30)->default('draft');
            $table->string('legacy_lifecycle_status', 100)->nullable();

            // Dimension 2: Collectibility status (DEC-007)
            $table->string('collectibility_status', 30)->default('unknown');
            $table->string('legacy_collectibility_status', 100)->nullable();

            // Dimension 3: Signing workflow status (DEC-003)
            $table->string('signing_status', 30)->default('not_prepared');
            $table->string('signature_summary', 20)->default('unknown');
            $table->string('legacy_signing_status', 100)->nullable();

            // Provenance and authorization
            $table->string('provenance', 255)->nullable();
            $table->string('approved_source', 255)->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Indexes (NO unique index on agreement_number per DEC-001)
            $table->index('agreement_number_normalized');
            $table->index(['partner_id', 'lifecycle_status']);
            $table->index('lifecycle_status');
            $table->index('collectibility_status');
            $table->index('signing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
