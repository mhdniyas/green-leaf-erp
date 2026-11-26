<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('public_uuid', 36)->unique();
            $table->string('credit_note_number', 80)->nullable()->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->date('business_date')->index();
            $table->string('reason', 50)->index();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['warehouse_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_credit_notes');
    }
};
