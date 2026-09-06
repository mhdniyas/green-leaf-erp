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
        Schema::table('employee_advance_requests', function (Blueprint $table): void {
            $table->uuid('request_uuid')->nullable()->unique()->after('id');
            $table->string('approved_fund_source', 30)->nullable()->after('fund_source');
            $table->foreignId('approved_company_account_id')
                ->nullable()
                ->after('approved_fund_source')
                ->constrained('cashbook_company_accounts')
                ->nullOnDelete();
            $table->json('review_snapshot')->nullable()->after('rule_snapshot');

            $table->index(['payroll_month', 'status', 'shop_id'], 'emp_adv_req_month_status_shop_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_advance_requests', function (Blueprint $table): void {
            $table->dropForeign(['approved_company_account_id']);
            $table->dropIndex('emp_adv_req_month_status_shop_idx');
            $table->dropUnique(['request_uuid']);
            $table->dropColumn([
                'request_uuid',
                'approved_fund_source',
                'approved_company_account_id',
                'review_snapshot',
            ]);
        });
    }
};
