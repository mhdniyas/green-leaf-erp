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
     */
    public function up(): void
    {
        if (! Schema::hasColumn('daily_price_approvals', 'grade')) {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->string('grade', 5)->default('A')->after('business_date')->index();
            });
        }

        // Add new unique index first so foreign key on product_id remains satisfied in MySQL
        try {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->unique(['product_id', 'business_date', 'grade'], 'daily_price_approvals_product_date_grade_unique');
            });
        } catch (Throwable $e) {
            // Already exists or unsupported
        }

        // Now safe to drop old unique constraint on (product_id, business_date)
        try {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->dropUnique('daily_price_approvals_product_date_unique');
            });
        } catch (Throwable $e) {
            // Ignore if index was already dropped or differs by driver
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('daily_price_approvals') && Schema::hasColumn('daily_price_approvals', 'grade')) {
            $nonACount = DB::table('daily_price_approvals')
                ->where(function ($query): void {
                    $query->whereNull('grade')
                        ->orWhere('grade', '!=', 'A');
                })
                ->count();

            if ($nonACount > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$nonACount} daily_price_approvals record(s) with non-Grade-A or NULL grade values. ".
                    'Reversing this migration would destroy multi-grade pricing records.'
                );
            }

            $duplicateIdentities = DB::table('daily_price_approvals')
                ->select('product_id', 'business_date')
                ->groupBy('product_id', 'business_date')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($duplicateIdentities > 0) {
                throw new RuntimeException(
                    "Rollback aborted: Found {$duplicateIdentities} duplicate (product_id, business_date) group(s) in daily_price_approvals. ".
                    'Re-creating the legacy unique index would violate database integrity.'
                );
            }
        }

        try {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->unique(['product_id', 'business_date'], 'daily_price_approvals_product_date_unique');
            });
        } catch (Throwable $e) {
            // Ignore
        }

        try {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->dropUnique('daily_price_approvals_product_date_grade_unique');
            });
        } catch (Throwable $e) {
            // Ignore
        }

        try {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->dropIndex('daily_price_approvals_grade_index');
            });
        } catch (Throwable $e) {
            // Ignore
        }

        if (Schema::hasColumn('daily_price_approvals', 'grade')) {
            Schema::table('daily_price_approvals', function (Blueprint $table): void {
                $table->dropColumn('grade');
            });
        }
    }
};
