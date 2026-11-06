<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('credit_note_number', 80)->unique();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('shop_invoice_id')->constrained('shop_invoices')->cascadeOnDelete();
            $table->date('credit_note_date')->index();
            $table->string('direction', 20)->default('credit')->index();
            $table->string('reason_type', 50)->default('other')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->string('inventory_action', 50)->default('none')->index();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('total_credit_amount', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'credit_note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_credit_notes');
    }
};
