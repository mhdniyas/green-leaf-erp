<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tray_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('tray_type_id')->constrained('tray_types')->cascadeOnDelete();
            $table->unsignedInteger('sent_qty')->default(0);
            $table->unsignedInteger('returned_qty')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'date', 'tray_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tray_movements');
    }
};
