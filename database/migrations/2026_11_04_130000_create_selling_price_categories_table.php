<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selling_price_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->decimal('markup_percentage', 8, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active', 'selling_price_categories_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selling_price_categories');
    }
};
