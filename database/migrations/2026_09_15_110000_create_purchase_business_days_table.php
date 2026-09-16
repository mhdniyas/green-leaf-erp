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
        Schema::create('purchase_business_days', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('status', 30)->default('open'); // open, closed, reopened
            $table->foreignId('opened_by')->constrained('users');
            $table->timestamp('opened_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->text('close_note')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'business_date'], 'pbd_warehouse_date_idx');
            $table->index(['status', 'business_date'], 'pbd_status_date_idx');
            $table->index(['warehouse_id', 'status'], 'pbd_warehouse_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_business_days');
    }
};
