<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('goods_received_id')->constrained('goods_received')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending'); // InvoiceStatus enum
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['goods_received_id']);
            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
