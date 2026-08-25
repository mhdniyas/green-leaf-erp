<?php

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
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_invoice_payment_requests', 'submission_uuid')) {
                $table->uuid('submission_uuid')->nullable()->unique()->after('requested_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_invoice_payment_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_invoice_payment_requests', 'submission_uuid')) {
                $table->dropUnique(['submission_uuid']);
                $table->dropColumn('submission_uuid');
            }
        });
    }
};
