<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overpayments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();

            // Partner is nullable per PRD §4 (non-partner/unidentified deposits stay unresolved)
            $table->foreignUuid('partner_id')->nullable()->constrained('partners')->nullOnDelete();

            // Financial amount: integer IDR, unapplied/ABT portion (PRD §2, §4)
            $table->unsignedBigInteger('unapplied_amount');

            // Proposed disposition & workflow status (DEC-006: policy OPEN)
            // Statuses: unresolved, verified_unapplied, disposition_proposed, disposition_approved, executed
            $table->string('proposed_disposition', 50)->nullable();
            $table->string('disposition_status', 50)->default('unresolved');

            // Evidence and authorization
            $table->text('evidence')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reason')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // Indexes
            $table->index('bank_transaction_id');
            $table->index('partner_id');
            $table->index('disposition_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overpayments');
    }
};
