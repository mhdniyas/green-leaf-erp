<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 50);
            $table->string('customer_type', 100)->nullable();
            $table->date('required_date')->nullable();
            $table->text('message');
            $table->string('source_page', 50);
            $table->timestamps();

            $table->index(['source_page', 'created_at']);
            $table->index('required_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_enquiries');
    }
};
