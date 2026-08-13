<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->date('business_date');
            $table->foreignId('entry_type_id')->constrained('ledger_entry_types');

            $table->decimal('amount', 15, 2);
            $table->string('direction');        // income | expense | transfer | settlement | adjustment
            $table->string('funding_source');   // sales | petty | company | company_later | bank | external | none

            // Booleans mirror the "what balances does it affect" question
            $table->boolean('affects_sales')->default(false);
            $table->boolean('affects_income')->default(false);
            $table->boolean('affects_expense')->default(false);
            $table->boolean('affects_pl')->default(false);

            // Computed deltas stored on the row so totals are always derived
            // from source rows, never recomputed with ad-hoc formulas at report time.
            $table->decimal('pl_delta', 15, 2)->default(0);
            $table->decimal('settlement_delta', 15, 2)->default(0);
            $table->string('settlement_direction')->default('none'); // increase | decrease | none
            $table->decimal('petty_delta', 15, 2)->default(0);
            $table->string('petty_direction')->default('none');
            $table->decimal('company_pending_delta', 15, 2)->default(0);
            $table->string('company_pending_direction')->default('none');

            // Secondary/generated entries
            $table->foreignId('parent_transaction_id')->nullable()
                ->constrained('shop_ledger_transactions')->nullOnDelete();
            $table->boolean('generated_by_rule')->default(false);

            $table->string('status')->default('posted'); // draft|submitted|posted|closed|void

            // Source-of-truth linkage
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('entered_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();

            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'business_date']);
            $table->index(['reference_type', 'reference_id']);

            // Prevents the same external source from being posted twice
            $table->unique(
                ['shop_id', 'reference_type', 'reference_id', 'entry_type_id'],
                'uniq_shop_source_entrytype'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_transactions');
    }
};
