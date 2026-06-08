<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50); // e.g. Farmer, Agent, Importer, Co-operative
            $table->string('category', 50)->default('own_purchase')->index();
            $table->boolean('is_default_purchase')->default(false)->index();
            $table->text('contact')->nullable();
            $table->string('payment_terms', 100)->nullable(); // e.g. Cash, 7-day, 30-day
            $table->decimal('quality_score', 5, 2)->default(100.00);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
