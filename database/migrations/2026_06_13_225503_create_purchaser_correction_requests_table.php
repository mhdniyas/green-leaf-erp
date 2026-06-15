<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchaser_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->date('business_date');
            $table->foreignId('shop_order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_approved_qty', 12, 2);
            $table->decimal('proposed_corrected_qty', 12, 2);
            $table->text('purchaser_note')->nullable();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['business_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchaser_correction_requests');
    }
};
