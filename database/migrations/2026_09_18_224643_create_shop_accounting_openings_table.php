<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_accounting_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->date('accounting_start_date')->index();
            $table->decimal('opening_shop_company_balance', 15, 2)->default(0.00);
            $table->string('opening_balance_direction', 32)->default('settled');
            $table->decimal('opening_allocation_pending', 15, 2)->default(0.00);
            $table->decimal('opening_petty_balance', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'accounting_start_date'], 'shop_accounting_openings_shop_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_accounting_openings');
    }
};
