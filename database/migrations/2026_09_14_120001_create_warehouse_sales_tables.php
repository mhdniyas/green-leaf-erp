<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouse_sales')) {
            Schema::create('warehouse_sales', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('invoice_number', 50)->unique();
                $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('warehouse_customers')->nullOnDelete();
                $table->string('customer_name_snapshot');
                $table->string('customer_phone_snapshot', 50)->nullable();
                $table->date('business_date');
                $table->foreignId('sold_by_user_id')->constrained('users')->restrictOnDelete();
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('balance_amount', 12, 2)->default(0);
                $table->string('status', 30)->default('confirmed'); // draft, confirmed, cancelled
                $table->text('notes')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();

                $table->index(['business_date', 'warehouse_id']);
                $table->index(['warehouse_id', 'status']);
                $table->index(['sold_by_user_id', 'business_date']);
                $table->index('customer_id');
            });
        }

        if (! Schema::hasTable('warehouse_sale_items')) {
            Schema::create('warehouse_sale_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('warehouse_sale_id')->constrained('warehouse_sales')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
                $table->string('grade', 20)->default('A');
                $table->decimal('entered_qty', 10, 3);
                $table->string('entered_unit', 20);
                $table->decimal('normalized_qty', 10, 3);
                $table->string('normalized_unit', 20)->default('kg');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->index(['warehouse_sale_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('warehouse_sale_payments')) {
            Schema::create('warehouse_sale_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('warehouse_sale_id')->constrained('warehouse_sales')->cascadeOnDelete();
                $table->string('payment_method', 30); // cash, upi, card, bank, credit, other
                $table->decimal('amount', 12, 2);
                $table->string('money_holder_type', 30)->default('company'); // company, user
                $table->foreignId('money_holder_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('company_account_id')->nullable()->constrained('cashbook_company_accounts')->nullOnDelete();
                $table->string('reference', 100)->nullable();
                $table->string('status', 30)->default('completed'); // completed, pending, cancelled
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['warehouse_sale_id', 'payment_method'], 'wsp_sale_pay_method_idx');
                $table->index(['money_holder_type', 'money_holder_user_id'], 'wsp_holder_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_sale_payments');
        Schema::dropIfExists('warehouse_sale_items');
        Schema::dropIfExists('warehouse_sales');
    }
};
