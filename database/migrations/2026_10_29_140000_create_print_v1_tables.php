<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_v1_settings', function (Blueprint $table) {
            $table->id();
            $table->string('section', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('settings');
            $table->timestamps();

            $table->unique(['section', 'user_id']);
        });

        Schema::create('print_v1_presets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('section', 32);
            $table->json('settings');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('print_v1_preset_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('section', 32)->default('sort-sheet');
            $table->json('preset_ids');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_v1_preset_batches');
        Schema::dropIfExists('print_v1_presets');
        Schema::dropIfExists('print_v1_settings');
    }
};
