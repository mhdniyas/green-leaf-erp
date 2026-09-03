<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('employee_category_id')->nullable()->change();
            $table->string('salary_type', 20)->nullable()->change();
            $table->decimal('monthly_salary', 12, 2)->nullable()->change();
            $table->decimal('daily_wage', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->foreignId('employee_category_id')->nullable(false)->change();
            $table->string('salary_type', 20)->nullable(false)->change();
            $table->decimal('monthly_salary', 12, 2)->nullable(false)->change();
            $table->decimal('daily_wage', 12, 2)->nullable(false)->change();
        });
    }
};
