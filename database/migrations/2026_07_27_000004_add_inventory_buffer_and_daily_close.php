<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('buffer_qty', 10, 2)->default(0.00)->after('base_price');
            $table->boolean('carryover_enabled')->default(false)->after('buffer_qty');
        });

        Schema::create('daily_inventory_close_lines', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('grade', 20)->default('A');
            $table->decimal('closing_qty', 12, 3)->default(0.000);
            $table->decimal('wastage_qty', 12, 3)->default(0.000);
            $table->decimal('carryover_qty', 12, 3)->default(0.000);
            $table->text('negative_note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_date', 'product_id', 'grade'], 'daily_inventory_close_unique');
            $table->index(['business_date', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_inventory_close_lines');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['buffer_qty', 'carryover_enabled']);
        });
    }
};
