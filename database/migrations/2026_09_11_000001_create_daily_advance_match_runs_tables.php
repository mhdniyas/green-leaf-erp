<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_advance_match_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->nullable()->unique();
            $table->string('client_submission_id', 100)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('bill_date')->index();
            $table->unsignedBigInteger('cursor')->nullable();
            $table->unsignedInteger('batch_size')->default(100);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('requested_plan_hash', 64)->index();
            $table->string('status', 30)->default('pending')->index();
            $table->json('plan_snapshot');
            $table->json('result_summary')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'bill_date']);
        });

        Schema::create('daily_advance_match_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('daily_advance_match_runs')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('goods_received_id')->constrained('goods_received')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->decimal('planned_base_qty', 12, 3)->default(0);
            $table->string('status', 30)->default('pending')->index();
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
        Schema::dropIfExists('daily_advance_match_run_items');
        Schema::dropIfExists('daily_advance_match_runs');
    }
};
