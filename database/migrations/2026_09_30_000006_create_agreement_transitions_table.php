<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Predecessor and successor agreement links
            $table->foreignUuid('predecessor_id')->constrained('agreements')->cascadeOnDelete();
            $table->foreignUuid('successor_id')->nullable()->constrained('agreements')->cascadeOnDelete();

            // Transition classification: amendment, rescheduling, closure, reversal
            $table->string('transition_type', 50);
            $table->date('effective_date');
            $table->text('reason')->nullable();

            // Approved component amounts transferred/restructured (integer IDR)
            $table->unsignedBigInteger('approved_principal_amount')->nullable();
            $table->unsignedBigInteger('approved_interest_amount')->nullable();
            $table->unsignedBigInteger('approved_admin_charge_amount')->nullable();

            // Authorization
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            $table->index('predecessor_id');
            $table->index('successor_id');
            $table->index('transition_type');
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_transitions');
    }
};
