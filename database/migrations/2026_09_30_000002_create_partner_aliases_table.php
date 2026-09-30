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
        Schema::create('partner_aliases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('partner_id')
                ->constrained('partners')
                ->cascadeOnDelete();

            // Original name as received — never modified
            $table->string('name_raw');
            // Lowered + trimmed for search
            $table->string('name_normalized');

            $table->string('source', 255)->nullable();

            // users.id is bigint, not UUID
            $table->foreignId('reviewer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // unreviewed | confirmed | rejected
            $table->string('state', 20)->default('unreviewed');

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            $table->index('name_normalized');
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_aliases');
    }
};
