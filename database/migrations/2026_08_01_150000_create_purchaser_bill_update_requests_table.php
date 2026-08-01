<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchaser_bill_update_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchaser_cart_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->date('current_business_date')->nullable();
            $table->date('requested_business_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
            $table->index(['purchase_invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchaser_bill_update_requests');
    }
};
