<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Correlation ID for request lifecycle tracing (PRD §4, §8)
            $table->uuid('correlation_id')->index();

            // Actor details
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 50)->default('user'); // user, system, guest
            $table->string('actor_identifier', 255)->nullable(); // email, cli/console, IP

            // Polymorphic target entity
            $table->string('target_type', 255)->nullable();
            $table->string('target_id', 255)->nullable();

            // Action verb (PRD §4, FR-06)
            $table->string('action', 50)->index();

            // Delta / payload with sensitive data masked (PRD §4, §8)
            $table->json('delta')->nullable();

            // Business rationale
            $table->text('reason')->nullable();

            // Client telemetry
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Append-only timestamp: no updated_at column (PRD §4 invariant 4)
            $table->timestamp('created_at')->useCurrent()->index();

            // Composite indexes for lookup
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
