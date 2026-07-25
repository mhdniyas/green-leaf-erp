<?php

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
        Schema::create('shop_accounting_period_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('closed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('closed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'period_start', 'period_end'], 'shop_period_closures_shop_period_unique');
            $table->index(['shop_id', 'period_start', 'period_end'], 'shop_period_closures_lookup_index');
        });

        Schema::dropIfExists('shop_accounting_invoice_splits');
        Schema::dropIfExists('shop_accounting_invoices');
        Schema::dropIfExists('shop_ownerships');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_accounting_period_closures');

        Schema::create('shop_ownerships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name', 255);
            $table->decimal('ownership_percent', 5, 2);
            $table->string('role_label', 100)->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'owner_name']);
        });

        Schema::create('shop_accounting_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('generated');
            $table->decimal('total_income', 12, 2)->default(0);
            $table->decimal('total_expense', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'period_start', 'period_end'], 'shop_accounting_invoices_shop_period_unique');
        });

        Schema::create('shop_accounting_invoice_splits', function (Blueprint $table): void {
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
};
