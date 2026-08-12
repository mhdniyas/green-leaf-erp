<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchaser_share_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('purchase_grade', 1)->default('A');
            $table->string('name', 80);
            $table->json('product_ids');
            $table->timestamps();

            $table->unique(['user_id', 'purchase_grade', 'name'], 'purchaser_share_presets_user_grade_name_unique');
            $table->index(['user_id', 'purchase_grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchaser_share_presets');
    }
};
