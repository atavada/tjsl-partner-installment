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
        Schema::create('source_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('file_hash', 64)->index();
            $table->string('filename', 255);
            $table->date('as_of_date');
            $table->string('parser_version', 50)->default('v1.0.0');
            $table->json('sheet_inventory')->nullable();
            $table->string('status', 50)->default('pending');
            $table->boolean('is_synthetic')->default(true);
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['file_hash', 'parser_version']);
            $table->index(['as_of_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_snapshots');
    }
};
