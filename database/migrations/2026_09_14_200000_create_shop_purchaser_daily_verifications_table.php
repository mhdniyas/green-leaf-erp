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
        if (! Schema::hasTable('shop_purchaser_daily_verifications')) {
            Schema::create('shop_purchaser_daily_verifications', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 64)->unique();
                $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
                $table->date('business_date');
                $table->foreignId('purchaser_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('open')->index();

                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();

                $table->foreignId('second_verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('second_verified_at')->nullable();

                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();

                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reopened_at')->nullable();
                $table->text('reopen_reason')->nullable();

                $table->json('summary_snapshot')->nullable();
                $table->json('checklist_snapshot')->nullable();

                $table->timestamps();

                $table->unique(['shop_id', 'business_date', 'purchaser_user_id'], 'shop_purchaser_day_unique');
            });
        }

        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_invoices', 'is_carried_forward')) {
                    $table->boolean('is_carried_forward')->default(false)->after('status');
                }
                if (! Schema::hasColumn('purchase_invoices', 'carry_forward_reason')) {
                    $table->text('carry_forward_reason')->nullable()->after('is_carried_forward');
                }
                if (! Schema::hasColumn('purchase_invoices', 'carried_forward_by')) {
                    $table->foreignId('carried_forward_by')->nullable()->after('carry_forward_reason')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_invoices', 'carried_forward_at')) {
                    $table->timestamp('carried_forward_at')->nullable()->after('carried_forward_by');
                }
                if (! Schema::hasColumn('purchase_invoices', 'original_business_date')) {
                    $table->date('original_business_date')->nullable()->after('carried_forward_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table): void {
                $columns = ['is_carried_forward', 'carry_forward_reason', 'carried_forward_by', 'carried_forward_at', 'original_business_date'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('purchase_invoices', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('shop_purchaser_daily_verifications');
    }
};
