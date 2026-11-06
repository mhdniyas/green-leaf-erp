<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_credit_notes', function (Blueprint $table): void {
            $table->string('accounting_mode', 30)
                ->default('legacy_invoice_linked')
                ->after('direction')
                ->index();
            $table->unsignedBigInteger('shop_invoice_id')->nullable()->change();
        });

        Schema::create('shop_credit_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_credit_note_id')->constrained('shop_credit_notes')->restrictOnDelete();
            $table->foreignId('shop_invoice_id')->constrained('shop_invoices')->restrictOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['shop_credit_note_id', 'shop_invoice_id'], 'sca_note_invoice_idx');
            $table->index(['shop_id', 'shop_invoice_id'], 'sca_shop_invoice_idx');
            $table->index(['shop_credit_note_id', 'reversed_at'], 'sca_note_reversed_idx');
        });

        // Enforce strictly positive application amount
        DB::statement('ALTER TABLE shop_credit_applications ADD CONSTRAINT chk_sca_positive_amount CHECK (amount > 0.00)');
    }

    public function down(): void
    {
        // Rollback safety preconditions:
        // 1. Block rollback if financial application records exist
        if (Schema::hasTable('shop_credit_applications') && DB::table('shop_credit_applications')->exists()) {
            throw new RuntimeException(
                'Cannot rollback migration: shop_credit_applications records exist. Rolling back would destroy financial application data.'
            );
        }

        // 2. Block rollback if any credit note has null shop_invoice_id or shop_level mode
        if (Schema::hasTable('shop_credit_notes')) {
            $hasUnsafeRecords = DB::table('shop_credit_notes')
                ->where('accounting_mode', '!=', 'legacy_invoice_linked')
                ->orWhereNull('shop_invoice_id')
                ->exists();

            if ($hasUnsafeRecords) {
                throw new RuntimeException(
                    'Cannot rollback migration: shop_credit_notes with null shop_invoice_id or non-legacy accounting mode exist. Rolling back would cause data loss or foreign key violations.'
                );
            }
        }

        Schema::dropIfExists('shop_credit_applications');

        Schema::table('shop_credit_notes', function (Blueprint $table): void {
            $table->dropIndex(['accounting_mode']);
            $table->dropColumn('accounting_mode');
            $table->unsignedBigInteger('shop_invoice_id')->nullable(false)->change();
        });
    }
};
