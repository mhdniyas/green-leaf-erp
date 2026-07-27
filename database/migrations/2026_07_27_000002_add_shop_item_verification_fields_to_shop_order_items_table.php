<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->foreignId('shop_verified_by')
                ->nullable()
                ->after('shop_reported_returned_qty')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('shop_verified_at')
                ->nullable()
                ->after('shop_verified_by');
            $table->text('shop_verification_note')
                ->nullable()
                ->after('shop_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_verified_by');
            $table->dropColumn(['shop_verified_at', 'shop_verification_note']);
        });
    }
};
