<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->unsignedSmallInteger('tenor_months')->nullable()->after('source_row_number');
            $table->date('loan_start_date')->nullable()->after('effective_date');
            $table->date('first_due_date')->nullable()->after('loan_start_date');
            $table->decimal('interest_rate_percent', 5, 2)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'tenor_months',
                'loan_start_date',
                'first_due_date',
                'interest_rate_percent',
            ]);
        });
    }
};
