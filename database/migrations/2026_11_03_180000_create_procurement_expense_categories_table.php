<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('sub_category_mode', 20)->default('none');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'procurement_expense_categories_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_expense_categories');
    }
};
