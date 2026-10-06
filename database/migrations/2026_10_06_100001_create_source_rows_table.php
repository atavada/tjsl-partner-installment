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
        Schema::create('source_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('snapshot_id')->constrained('source_snapshots')->cascadeOnDelete();
            $table->string('sheet_name', 100);
            $table->unsignedInteger('row_number');
            $table->string('cell_coordinates', 50)->nullable();
            $table->json('raw_values');
            $table->json('formula_text')->nullable();
            $table->json('cached_values')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->json('parse_warnings')->nullable();
            $table->timestamps();

            $table->index(['snapshot_id', 'sheet_name', 'row_number']);
            $table->index(['sheet_name', 'row_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_rows');
    }
};
