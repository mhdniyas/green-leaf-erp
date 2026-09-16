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
        Schema::table('shops', function (Blueprint $table): void {
            if (! Schema::hasColumn('shops', 'shop_purchasing_enabled')) {
                $table->boolean('shop_purchasing_enabled')->default(false)->after('allow_grade_b_purchase');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoices', 'shop_id')) {
                $table->foreignId('shop_id')->nullable()->after('supplier_id')->constrained('shops')->nullOnDelete();
            }
        });

        Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_ledger_entry_settings', 'edit_policy')) {
                $table->string('edit_policy')->default('past_days_allowed')->after('is_readonly');
            }
        });

        if (! Schema::hasTable('shop_suppliers')) {
            Schema::create('shop_suppliers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['shop_id', 'supplier_id']);
            });
        }

        if (! Schema::hasTable('shop_vendor_payables')) {
            Schema::create('shop_vendor_payables', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_uuid')->unique();
                $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->date('business_date')->index();
                $table->decimal('original_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('outstanding_amount', 12, 2)->default(0);
                $table->string('status', 30)->default('pending')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['shop_id', 'business_date']);
                $table->index(['shop_id', 'supplier_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_vendor_payables');
        Schema::dropIfExists('shop_suppliers');

        Schema::table('shop_ledger_entry_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_ledger_entry_settings', 'edit_policy')) {
                $table->dropColumn('edit_policy');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoices', 'shop_id')) {
                $table->dropConstrainedForeignId('shop_id');
            }
        });

        Schema::table('shops', function (Blueprint $table): void {
            if (Schema::hasColumn('shops', 'shop_purchasing_enabled')) {
                $table->dropColumn('shop_purchasing_enabled');
            }
        });
    }
};
