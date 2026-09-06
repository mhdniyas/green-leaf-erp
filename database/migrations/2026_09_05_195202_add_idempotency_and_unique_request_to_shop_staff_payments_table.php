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
        Schema::table('shop_staff_payments', function (Blueprint $table): void {
            $table->uuid('request_uuid')->nullable()->unique()->after('id');
            $table->unique('employee_advance_request_id', 'shop_staff_payments_advance_request_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_staff_payments', function (Blueprint $table): void {
            $table->dropUnique('shop_staff_payments_advance_request_unique');
            $table->dropUnique(['request_uuid']);
            $table->dropColumn('request_uuid');
        });
    }
};
