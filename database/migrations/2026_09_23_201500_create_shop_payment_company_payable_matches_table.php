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
        Schema::create('shop_payment_company_payable_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('payment_request_id')->constrained('shop_invoice_payment_requests')->cascadeOnDelete();
            $table->date('payable_business_date');
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('active');
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('matched_at');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reversed_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'payable_business_date', 'status'], 'sp_cpm_shop_date_status_idx');
            $table->index(['payment_request_id', 'status'], 'sp_cpm_payment_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_payment_company_payable_matches');
    }
};
