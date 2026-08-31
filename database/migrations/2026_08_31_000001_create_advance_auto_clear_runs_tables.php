<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_auto_clear_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->nullable()->unique();
            $table->string('client_submission_id', 100)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('requested_plan_hash', 64)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('plan_snapshot');
            $table->json('result_summary')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('advance_auto_clear_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('advance_auto_clear_runs')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('execution_mode', 40);
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('source_goods_received_id')->nullable()->constrained('goods_received')->nullOnDelete();
            $table->decimal('planned_base_qty', 12, 3)->default(0);
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('result_goods_received_id')->nullable()->constrained('goods_received')->nullOnDelete();
            $table->string('reason_code', 60)->nullable();
            $table->json('result_payload')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_auto_clear_run_items');
        Schema::dropIfExists('advance_auto_clear_runs');
    }
};
