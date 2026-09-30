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
        Schema::create('partners', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Official partner number — raw string preserving leading zeros
            $table->string('partner_no_id', 50)->nullable();
            // Normalized for lookup: trimmed + uppercased
            $table->string('partner_no_id_normalized', 50)->nullable();

            // National identity number — raw string preserving leading zeros
            $table->string('nik', 30)->nullable();
            // Normalized for lookup: trimmed
            $table->string('nik_normalized', 30)->nullable();

            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('business_type', 100)->nullable();
            $table->string('region', 100)->nullable();

            // unverified | pending | verified
            $table->string('verification_state', 20)->default('unverified');
            $table->string('provenance', 255)->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // MySQL unique allows multiple NULLs — nullable-in-staging works
            $table->unique('partner_no_id_normalized');
            $table->index('nik_normalized');
            $table->index('verification_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
