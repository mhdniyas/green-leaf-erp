<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_accounting_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_accounting_entry_id')->constrained('shop_accounting_entries')->cascadeOnDelete();
            $table->foreignId('shop_accounting_category_id')->constrained('shop_accounting_categories')->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_accounting_entry_lines');
    }
};
