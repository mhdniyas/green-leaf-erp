<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_monthly_report_expense_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 80);
            $table->string('source_key', 120);
            $table->string('report_bucket', 40);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['source_type', 'source_key'], 'cb_mr_exp_mappings_type_key_unique');
            $table->index('report_bucket', 'cb_mr_exp_mappings_bucket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_monthly_report_expense_mappings');
    }
};
