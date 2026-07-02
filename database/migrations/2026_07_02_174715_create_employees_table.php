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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('employee_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('staff_area', 20);
            $table->string('employment_status', 30)->default('active');
            $table->date('joined_on')->nullable();
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->boolean('is_user_linked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['staff_area', 'employment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
