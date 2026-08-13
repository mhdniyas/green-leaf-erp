<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_ledger_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('slug')->nullable()->unique();
            $table->unsignedBigInteger('shop_id')->unique();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('profile_template')->nullable(); // e.g. 'owned_standard', 'managed_outlet'
            $table->boolean('enabled')->default(true);
            $table->string('closing_mode')->default('manual'); // manual | auto
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_profiles');
    }
};
