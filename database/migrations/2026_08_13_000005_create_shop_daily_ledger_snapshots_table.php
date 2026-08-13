<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_daily_ledger_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->date('business_date');

            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('net_pl', 15, 2)->default(0);

            $table->decimal('opening_petty', 15, 2)->default(0);
            $table->decimal('petty_in', 15, 2)->default(0);
            $table->decimal('petty_out', 15, 2)->default(0);
            $table->decimal('closing_petty', 15, 2)->default(0);

            $table->decimal('opening_shop_position', 15, 2)->default(0);
            $table->decimal('settlement_increase', 15, 2)->default(0);
            $table->decimal('settlement_decrease', 15, 2)->default(0);
            $table->decimal('closing_shop_position', 15, 2)->default(0);

            $table->decimal('opening_company_pending', 15, 2)->default(0);
            $table->decimal('company_pending_in', 15, 2)->default(0);
            $table->decimal('company_pending_out', 15, 2)->default(0);
            $table->decimal('closing_company_pending', 15, 2)->default(0);

            $table->string('status')->default('open'); // open | closed | reopened
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_daily_ledger_snapshots');
    }
};
