<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_warehouse', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'warehouse_id']);
            $table->index(['warehouse_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_warehouse');
    }
};
