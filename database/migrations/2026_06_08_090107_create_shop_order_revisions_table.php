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
        Schema::create('shop_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('status', 30)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_order_id', 'revision_no']);
            $table->index(['shop_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_order_revisions');
    }
};
