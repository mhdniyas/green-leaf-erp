<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->string('delivery_review_status', 30)
                ->default('not_started')
                ->after('delivery_status')
                ->index();
            $table->foreignId('shop_checked_by')
                ->nullable()
                ->after('delivery_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('shop_checked_at')
                ->nullable()
                ->after('shop_checked_by');
            $table->foreignId('admin_reviewed_by')
                ->nullable()
                ->after('shop_checked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('admin_reviewed_at')
                ->nullable()
                ->after('admin_reviewed_by');
            $table->text('admin_review_note')
                ->nullable()
                ->after('admin_reviewed_at');
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->decimal('shop_reported_received_qty', 10, 2)
                ->nullable()
                ->after('delivered_qty');
            $table->decimal('shop_reported_missing_qty', 10, 2)
                ->default(0.00)
                ->after('shop_reported_received_qty');
            $table->decimal('shop_reported_damaged_qty', 10, 2)
                ->default(0.00)
                ->after('shop_reported_missing_qty');
            $table->decimal('shop_reported_returned_qty', 10, 2)
                ->default(0.00)
                ->after('shop_reported_damaged_qty');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'shop_reported_received_qty',
                'shop_reported_missing_qty',
                'shop_reported_damaged_qty',
                'shop_reported_returned_qty',
            ]);
        });

        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_checked_by');
            $table->dropConstrainedForeignId('admin_reviewed_by');
            $table->dropColumn([
                'delivery_review_status',
                'shop_checked_at',
                'admin_reviewed_at',
                'admin_review_note',
            ]);
        });
    }
};
