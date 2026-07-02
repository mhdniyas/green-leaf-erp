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
        Schema::create('employee_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 50)->unique();
            $table->string('staff_area', 20);
            $table->decimal('default_monthly_salary', 12, 2)->default(0);
            $table->decimal('present_day_weight', 5, 2)->default(1);
            $table->decimal('half_day_weight', 5, 2)->default(0.5);
            $table->decimal('paid_leave_weight', 5, 2)->default(1);
            $table->decimal('absent_day_weight', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_categories');
    }
};
