<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agreement_id')->constrained('agreements')->cascadeOnDelete();
            $table->foreignUuid('transition_id')->nullable()->constrained('agreement_transitions')->nullOnDelete();

            // File reference in private storage
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('checksum_sha256', 64);

            // Document metadata
            $table->string('document_type', 50)->default('contract');
            $table->unsignedInteger('document_version')->default(1);
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();

            // Workflow status at document level (DEC-003)
            $table->string('signing_status', 30)->default('not_prepared');
            $table->string('signature_summary', 20)->default('unknown');
            $table->text('notes')->nullable();

            // Optimistic concurrency
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();

            $table->index('agreement_id');
            $table->index('checksum_sha256');
            $table->index('document_type');
            $table->index('signing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_documents');
    }
};
