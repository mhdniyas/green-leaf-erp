<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_credit_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_credit_notes', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0.00)->after('inventory_action');
            }
            if (! Schema::hasColumn('shop_credit_notes', 'discount_reason')) {
                $table->string('discount_reason', 255)->nullable()->after('discount_amount');
            }
            if (! Schema::hasColumn('shop_credit_notes', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(1)->after('total_credit_amount');
            }
        });

        Schema::table('shop_credit_note_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_credit_note_items', 'line_type')) {
                $table->string('line_type', 30)->default('product_adjustment')->after('shop_credit_note_id')->index();
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'reason_type')) {
                $table->string('reason_type', 50)->nullable()->after('line_type');
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'source_shop_invoice_id')) {
                $table->foreignId('source_shop_invoice_id')->nullable()->after('shop_invoice_item_id')->constrained('shop_invoices')->nullOnDelete();
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'credit_rate')) {
                $table->decimal('credit_rate', 10, 2)->default(0.00)->after('adjusted_unit_price');
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'received_grade')) {
                $table->string('received_grade', 5)->nullable()->after('product_grade');
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'received_quantity')) {
                $table->decimal('received_quantity', 12, 4)->default(0.0000)->after('adjusted_quantity');
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'receipt_status')) {
                $table->string('receipt_status', 30)->default('not_applicable')->after('inventory_action');
            }
            if (! Schema::hasColumn('shop_credit_note_items', 'physical_receipt_notes')) {
                $table->text('physical_receipt_notes')->nullable()->after('notes');
            }

            // Make product_id and unit nullable for standalone commercial discount lines
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->string('unit', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_credit_note_items', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_credit_note_items', 'source_shop_invoice_id')) {
                $table->dropForeign(['source_shop_invoice_id']);
                $table->dropColumn('source_shop_invoice_id');
            }
            $dropCols = [];
            foreach (['line_type', 'reason_type', 'credit_rate', 'received_grade', 'received_quantity', 'receipt_status', 'physical_receipt_notes'] as $col) {
                if (Schema::hasColumn('shop_credit_note_items', $col)) {
                    $dropCols[] = $col;
                }
            }
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });

        Schema::table('shop_credit_notes', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_credit_notes', 'lock_version')) {
                $table->dropColumn('lock_version');
            }
        });
    }
};
