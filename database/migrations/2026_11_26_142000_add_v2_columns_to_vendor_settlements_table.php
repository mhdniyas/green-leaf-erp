<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settlements', function (Blueprint $table): void {
            $table->decimal('purchase_credit_used_amount', 14, 2)->default(0.00)->after('vendor_advance_used_amount');
            $table->string('submission_token', 100)->nullable()->unique()->after('public_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_settlements', function (Blueprint $table): void {
            $table->dropUnique(['submission_token']);
            $table->dropColumn([
                'purchase_credit_used_amount',
                'submission_token',
            ]);
        });
    }
};
