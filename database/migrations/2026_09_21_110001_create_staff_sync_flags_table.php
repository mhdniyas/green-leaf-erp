<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_sync_flags', function (Blueprint $table): void {
            $table->id();
            $table->string('flag_code', 64)->index();
            $table->string('severity', 16)->default('danger'); // danger, warning, info
            $table->string('source_type', 128)->index();
            $table->unsignedBigInteger('source_id')->index();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('payment_date')->nullable()->index();
            $table->string('status', 24)->default('open')->index(); // open, resolved, dismissed
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sync_flags');
    }
};
