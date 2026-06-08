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
        Schema::create('daily_product_price_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_product_price_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_price_group_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 5)->default('A')->index();
            $table->decimal('old_price', 10, 2)->nullable();
            $table->decimal('new_price', 10, 2);
            $table->decimal('old_margin_percent', 6, 2)->nullable();
            $table->decimal('new_margin_percent', 6, 2)->nullable();
            $table->string('change_type', 20)->default('manual');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_product_price_revisions');
    }
};
