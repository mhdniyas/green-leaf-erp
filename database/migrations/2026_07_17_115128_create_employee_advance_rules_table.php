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
        Schema::create('employee_advance_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->default('Default advance rule');
            $table->unsignedInteger('minimum_present_days')->default(20);
            $table->decimal('advance_percent', 5, 2)->default(50);
            $table->boolean('default_from_petty_cash')->default(true);
            $table->boolean('allow_negative_shop_balance')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_advance_rules');
    }
};
