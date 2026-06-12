<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->string('grn_number', 100)->unique();
            $table->string('status', 30)->default('approved');
            $table->text('rejection_remarks')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('received_at');
            $table->timestamp('approved_at')->nullable();
            $table->decimal('transport_cost', 10, 2)->default(0.00);
            $table->decimal('labour_cost', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_order_id']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received');
    }
};
