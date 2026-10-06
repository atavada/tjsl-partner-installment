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
        Schema::create('metric_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 100)->index();
            $table->string('name', 255);
            $table->string('version', 20)->default('v1');
            $table->text('formula_expression');
            $table->text('description')->nullable();
            $table->text('numerator')->nullable();
            $table->text('denominator')->nullable();
            $table->text('date_semantics')->nullable();
            $table->text('included_population')->nullable();
            $table->text('excluded_population')->nullable();
            $table->string('decision_ref', 100)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by', 255)->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_definitions');
    }
};
