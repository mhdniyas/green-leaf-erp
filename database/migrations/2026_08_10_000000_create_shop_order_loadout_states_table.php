<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_order_loadout_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->foreignId('initialized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shop_order_id', 'warehouse_id'], 'shop_order_loadout_states_order_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_loadout_states');
    }
};
