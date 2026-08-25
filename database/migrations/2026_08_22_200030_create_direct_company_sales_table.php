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
        Schema::create('direct_company_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->uuid('request_uuid')->unique();
            $table->date('business_date');
            $table->string('customer_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->foreignId('company_account_id')->constrained('cashbook_company_accounts')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reconciliation_status')->default('pending');
            $table->boolean('is_finalized')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_company_sales');
    }
};
