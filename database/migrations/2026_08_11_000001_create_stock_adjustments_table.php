<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('business_date');
            $table->decimal('system_qty', 12, 3);
            $table->decimal('counted_qty', 12, 3);
            $table->decimal('variance_qty', 12, 3);
            $table->string('category', 30);
            $table->text('notes');
            $table->timestamps();

            $table->index(['business_date', 'category']);
            $table->index(['product_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
