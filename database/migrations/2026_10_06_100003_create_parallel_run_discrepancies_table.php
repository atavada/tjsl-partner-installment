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
        Schema::create('parallel_run_discrepancies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('run_date');
            $table->foreignUuid('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->json('legacy_values');
            $table->json('ledger_values');
            $table->bigInteger('variance_amount');
            $table->string('variance_type', 50);
            $table->string('status', 50)->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['run_date', 'status']);
            $table->index(['agreement_id', 'variance_type']);
            $table->index('variance_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parallel_run_discrepancies');
    }
};
