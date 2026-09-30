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
        Schema::create('virtual_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('partner_id')
                ->constrained('partners')
                ->cascadeOnDelete();

            // Raw VA number — exact string, leading zeros preserved
            $table->string('va_number', 50);
            // Trimmed for lookup
            $table->string('va_number_normalized', 50);

            $table->string('provider', 100)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('evidence')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            // No global unique — PRD §4: no assumption of global or timeless uniqueness
            $table->index('va_number_normalized');
            $table->index('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('virtual_accounts');
    }
};
