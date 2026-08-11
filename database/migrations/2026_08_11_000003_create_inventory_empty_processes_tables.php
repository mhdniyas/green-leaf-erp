<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_empty_processes', function (Blueprint $table): void {
            $table->id(); $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_records')->default(0); $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0); $table->unsignedInteger('failed_records')->default(0);
            $table->foreignId('current_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at')->nullable(); $table->timestamp('completed_at')->nullable(); $table->text('error_message')->nullable(); $table->timestamps();
            $table->index('status');
        });
        Schema::create('inventory_empty_process_items', function (Blueprint $table): void {
            $table->id(); $table->foreignId('process_id')->constrained('inventory_empty_processes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete(); $table->string('status', 20)->default('pending'); $table->text('error_message')->nullable(); $table->timestamps();
            $table->unique(['process_id', 'product_id']); $table->index(['process_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('inventory_empty_process_items'); Schema::dropIfExists('inventory_empty_processes'); }
};
