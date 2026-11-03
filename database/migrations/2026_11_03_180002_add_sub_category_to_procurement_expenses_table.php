<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_expenses', function (Blueprint $table): void {
            $table->foreignId('procurement_expense_sub_category_id')
                ->nullable()
                ->after('funding_source');
            $table->string('sub_category', 120)->nullable()->after('procurement_expense_sub_category_id');

            $table->foreign('procurement_expense_sub_category_id', 'proc_expenses_sub_cat_fk')
                ->references('id')
                ->on('procurement_expense_sub_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_expenses', function (Blueprint $table): void {
            $table->dropForeign('proc_expenses_sub_cat_fk');
            $table->dropColumn(['procurement_expense_sub_category_id', 'sub_category']);
        });
    }
};
