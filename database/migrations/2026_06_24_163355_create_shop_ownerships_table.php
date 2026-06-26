<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_ownerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name', 255);
            $table->decimal('ownership_percent', 5, 2);
            $table->string('role_label', 100)->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'owner_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ownerships');
    }
};
