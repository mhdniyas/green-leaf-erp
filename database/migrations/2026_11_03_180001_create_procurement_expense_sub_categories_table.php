<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_expense_sub_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procurement_expense_category_id');
            $table->string('name', 120);
            $table->string('code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('procurement_expense_category_id', 'proc_exp_sub_cat_cat_fk')
                ->references('id')
                ->on('procurement_expense_categories')
                ->restrictOnDelete();

            $table->unique(['procurement_expense_category_id', 'name'], 'proc_exp_sub_cat_cat_name_unique');
            $table->index(['procurement_expense_category_id', 'is_active', 'sort_order'], 'proc_exp_sub_cat_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_expense_sub_categories');
    }
};
