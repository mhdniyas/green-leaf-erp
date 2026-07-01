<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_accounting_invoice_splits')) {
            return;
        }

        Schema::create('shop_accounting_invoice_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_accounting_invoice_id')
                ->constrained(table: 'shop_accounting_invoices', indexName: 'sais_invoice_fk')
                ->cascadeOnDelete();
            $table->foreignId('shop_ownership_id')
                ->constrained(table: 'shop_ownerships', indexName: 'sais_owner_fk')
                ->cascadeOnDelete();
            $table->string('owner_name_snapshot', 255);
            $table->decimal('ownership_percent_snapshot', 5, 2);
            $table->decimal('share_amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_accounting_invoice_splits');
    }
};
