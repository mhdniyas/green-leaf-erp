<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_petty_cash_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->decimal('amount', 12, 2);
            $table->string('source', 20)->default('auto');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['shop_id', 'business_date']);
            $table->index(['shop_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_petty_cash_expenses');
    }
};
