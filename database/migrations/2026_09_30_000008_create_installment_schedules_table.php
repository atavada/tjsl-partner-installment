<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agreement_id')->constrained('agreements')->cascadeOnDelete();

            $table->unsignedInteger('installment_number');
            $table->date('due_date');

            // Due components (integer IDR, default 0)
            $table->unsignedBigInteger('principal_due')->default(0);
            $table->unsignedBigInteger('interest_due')->default(0);
            $table->unsignedBigInteger('admin_charge_due')->default(0);
            $table->unsignedBigInteger('other_charge_due')->default(0);
            $table->unsignedBigInteger('total_due')->default(0);

            // Paid tracking (schema-only, calculation blocked on DEC-008)
            $table->unsignedBigInteger('principal_paid')->default(0);
            $table->unsignedBigInteger('interest_paid')->default(0);
            $table->unsignedBigInteger('admin_charge_paid')->default(0);
            $table->unsignedBigInteger('other_charge_paid')->default(0);
            $table->unsignedBigInteger('total_paid')->default(0);

            // Status: pending, paid, partially_paid, overdue, cancelled
            $table->string('status', 30)->default('pending');
            $table->string('policy_version', 50)->nullable();
            $table->boolean('is_calculated')->default(false);

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            $table->unique(['agreement_id', 'installment_number']);
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_schedules');
    }
};
