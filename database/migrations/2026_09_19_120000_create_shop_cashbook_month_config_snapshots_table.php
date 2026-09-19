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
        if (! Schema::hasTable('shop_cashbook_month_config_snapshots')) {
            Schema::create('shop_cashbook_month_config_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->string('month', 7); // Format: YYYY-MM
                $table->string('status', 32)->default('active'); // active, finalized, legacy_reconstructed
                $table->string('source', 64)->default('live_frozen'); // live_frozen, pre_mutation, period_closure, legacy_reconstruction
                $table->json('config_data');
                $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['shop_id', 'month'], 'shop_month_config_unique');
                $table->index(['shop_id', 'month']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_cashbook_month_config_snapshots');
    }
};
