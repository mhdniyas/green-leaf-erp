<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'funding_source')) {
                $table->string('funding_source', 20)->nullable()->after('is_loan_entry');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_status')) {
                $table->string('company_payable_status', 30)->nullable()->after('funding_source');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_payable_amount')) {
                $table->decimal('company_payable_amount', 14, 2)->nullable()->after('company_payable_status');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_approved_amount')) {
                $table->decimal('company_approved_amount', 14, 2)->nullable()->after('company_payable_amount');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_settled_amount')) {
                $table->decimal('company_settled_amount', 14, 2)->nullable()->after('company_approved_amount');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_approved_by')) {
                $table->foreignId('company_approved_by')->nullable()->after('company_settled_amount')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_approved_at')) {
                $table->timestamp('company_approved_at')->nullable()->after('company_approved_by');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_rejected_by')) {
                $table->foreignId('company_rejected_by')->nullable()->after('company_approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_rejected_at')) {
                $table->timestamp('company_rejected_at')->nullable()->after('company_rejected_by');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_rejection_reason')) {
                $table->text('company_rejection_reason')->nullable()->after('company_rejected_at');
            }
            if (! Schema::hasColumn('shop_accounting_entry_lines', 'company_settlement_status')) {
                $table->string('company_settlement_status', 30)->nullable()->after('company_rejection_reason');
            }
        });

        DB::table('shop_accounting_entry_lines')
            ->where('type', 'expense')
            ->whereNull('funding_source')
            ->update(['funding_source' => 'sales']);

        DB::table('shop_accounting_entry_lines')
            ->where('type', 'expense')
            ->where('is_loan_entry', true)
            ->where(function ($query): void {
                $query->whereNull('funding_source')->orWhere('funding_source', 'sales');
            })
            ->update(['funding_source' => 'petty']);

        if (! Schema::hasTable('company_payable_settlements')) {
            Schema::create('company_payable_settlements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('shop_accounting_entry_line_id');
                $table->unsignedBigInteger('shop_id');
                $table->string('settlement_type', 40);
                $table->decimal('amount', 14, 2);
                $table->date('settlement_date');
                $table->unsignedBigInteger('shop_invoice_payment_request_id')->nullable();
                $table->unsignedBigInteger('company_accounting_entry_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->unsignedBigInteger('payment_account_id')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->foreign('shop_accounting_entry_line_id', 'cps_line_fk')
                    ->references('id')
                    ->on('shop_accounting_entry_lines')
                    ->cascadeOnDelete();
                $table->foreign('shop_id', 'cps_shop_fk')
                    ->references('id')
                    ->on('shops')
                    ->cascadeOnDelete();
                $table->foreign('shop_invoice_payment_request_id', 'cps_payment_req_fk')
                    ->references('id')
                    ->on('shop_invoice_payment_requests')
                    ->nullOnDelete();
                $table->foreign('company_accounting_entry_id', 'cps_company_entry_fk')
                    ->references('id')
                    ->on('company_accounting_entries')
                    ->nullOnDelete();
                $table->foreign('journal_entry_id', 'cps_journal_fk')
                    ->references('id')
                    ->on('journal_entries')
                    ->nullOnDelete();
                $table->foreign('created_by', 'cps_created_by_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();

                $table->index(['shop_id', 'settlement_date'], 'cps_shop_date_idx');
                $table->index(['shop_accounting_entry_line_id', 'settlement_type'], 'cps_line_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payable_settlements');

        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            foreach ([
                'company_settlement_status',
                'company_rejection_reason',
                'company_rejected_at',
                'company_rejected_by',
                'company_approved_at',
                'company_approved_by',
                'company_settled_amount',
                'company_approved_amount',
                'company_payable_amount',
                'company_payable_status',
                'funding_source',
            ] as $column) {
                if (Schema::hasColumn('shop_accounting_entry_lines', $column)) {
                    if (in_array($column, ['company_approved_by', 'company_rejected_by'], true)) {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
