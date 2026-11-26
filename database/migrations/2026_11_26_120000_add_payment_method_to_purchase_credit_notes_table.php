<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_credit_notes', function (Blueprint $table): void {
            $table->string('payment_method', 50)->nullable()->after('company_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_credit_notes', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }
};
