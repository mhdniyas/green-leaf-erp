<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_operation_verifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('section', 50)->index(); // e.g. 'shop_purchasing', 'central_purchasing', 'receiving', etc.
            $table->date('business_date')->index();
            $table->string('scope_type', 50)->nullable(); // e.g. 'shop', 'warehouse', 'company'
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable()->index();
            $table->string('status', 30)->default('open')->index();

            // Verification cycle 1
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Verification cycle 2 (Second Verification)
            $table->foreignId('second_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('second_verified_at')->nullable();

            // Finalization
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();

            // Reopening
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();

            // Snapshots & metadata
            $table->json('summary_snapshot')->nullable();
            $table->json('checklist_snapshot')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['section', 'business_date', 'scope_type', 'scope_id', 'responsible_user_id'],
                'daily_op_verifications_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_operation_verifications');
    }
};
