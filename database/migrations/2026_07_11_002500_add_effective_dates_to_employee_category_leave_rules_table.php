<?php

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
        Schema::table('employee_category_leave_rules', function (Blueprint $table): void {
            $table->date('effective_from')->nullable()->after('allocation_frequency');
            $table->date('effective_to')->nullable()->after('effective_from');
            $table->date('carry_forward_expiry_date')->nullable()->after('carry_forward_expiry_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_category_leave_rules', function (Blueprint $table): void {
            $table->dropColumn([
                'effective_from',
                'effective_to',
                'carry_forward_expiry_date',
            ]);
        });
    }
};
