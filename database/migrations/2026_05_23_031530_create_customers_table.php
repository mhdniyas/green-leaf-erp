<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // Retailer, Wholesaler, Restaurant, Supermarket
            $table->string('contact');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms')->default('COD'); // COD, Net 7, Net 15, Net 30
            $table->decimal('credit_limit', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
