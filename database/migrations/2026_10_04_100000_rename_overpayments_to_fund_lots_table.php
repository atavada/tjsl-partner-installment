<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refactors Overpayment model into FundLot model per DEC-006 (2026-10-03).
     * Distinguishes ABT, identified-unallocated, and true excess lots.
     */
    public function up(): void
    {
        $targetTable = Schema::hasTable('fund_lots') ? 'fund_lots' : 'overpayments';

        // Add new columns if not present
        Schema::table($targetTable, function (Blueprint $table) use ($targetTable) {
            if (! Schema::hasColumn($targetTable, 'amount') && Schema::hasColumn($targetTable, 'unapplied_amount')) {
                $table->renameColumn('unapplied_amount', 'amount');
            }
            if (! Schema::hasColumn($targetTable, 'lot_type')) {
                $table->string('lot_type', 50)->default('abt')->after('partner_id')->index();
            }
            if (! Schema::hasColumn($targetTable, 'identified_by_id')) {
                $table->foreignId('identified_by_id')->nullable()->after('evidence')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn($targetTable, 'identified_at')) {
                $table->timestamp('identified_at')->nullable()->after('identified_by_id');
            }
            if (! Schema::hasColumn($targetTable, 'identification_evidence')) {
                $table->text('identification_evidence')->nullable()->after('identified_at');
            }
            if (! Schema::hasColumn($targetTable, 'source_agreement_id')) {
                $table->foreignUuid('source_agreement_id')->nullable()->after('identification_evidence')->constrained('agreements')->nullOnDelete()->index();
            }
        });

        // Drop legacy constraints via raw SQL to handle table renaming gracefully
        try {
            DB::statement("ALTER TABLE `{$targetTable}` DROP FOREIGN KEY `{$targetTable}_approved_by_id_foreign`");
        } catch (Throwable) {
            try {
                DB::statement("ALTER TABLE `{$targetTable}` DROP FOREIGN KEY `overpayments_approved_by_id_foreign`");
            } catch (Throwable) {
            }
        }

        try {
            DB::statement("ALTER TABLE `{$targetTable}` DROP INDEX `{$targetTable}_disposition_status_index`");
        } catch (Throwable) {
            try {
                DB::statement("ALTER TABLE `{$targetTable}` DROP INDEX `overpayments_disposition_status_index`");
            } catch (Throwable) {
            }
        }

        // Drop legacy columns
        foreach (['approved_by_id', 'disposition_status', 'proposed_disposition', 'approved_at'] as $col) {
            if (Schema::hasColumn($targetTable, $col)) {
                try {
                    DB::statement("ALTER TABLE `{$targetTable}` DROP COLUMN `{$col}`");
                } catch (Throwable) {
                }
            }
        }

        // Rename table if needed
        if ($targetTable === 'overpayments') {
            Schema::rename('overpayments', 'fund_lots');
        }

        // Backfill existing rows: partner known -> identified_unallocated, else -> abt
        DB::table('fund_lots')
            ->whereNotNull('partner_id')
            ->update(['lot_type' => 'identified_unallocated']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fund_lots')) {
            Schema::table('fund_lots', function (Blueprint $table) {
                if (Schema::hasColumn('fund_lots', 'lot_type')) {
                    $table->dropColumn('lot_type');
                }
                if (Schema::hasColumn('fund_lots', 'source_agreement_id')) {
                    $table->dropConstrainedForeignId('source_agreement_id');
                }
                if (Schema::hasColumn('fund_lots', 'identified_by_id')) {
                    $table->dropConstrainedForeignId('identified_by_id');
                }
                if (Schema::hasColumn('fund_lots', 'identified_at')) {
                    $table->dropColumn('identified_at');
                }
                if (Schema::hasColumn('fund_lots', 'identification_evidence')) {
                    $table->dropColumn('identification_evidence');
                }

                $table->string('proposed_disposition', 50)->nullable();
                $table->string('disposition_status', 50)->default('unresolved')->index();
                $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();

                if (Schema::hasColumn('fund_lots', 'amount')) {
                    $table->renameColumn('amount', 'unapplied_amount');
                }
            });

            Schema::rename('fund_lots', 'overpayments');
        }
    }
};
