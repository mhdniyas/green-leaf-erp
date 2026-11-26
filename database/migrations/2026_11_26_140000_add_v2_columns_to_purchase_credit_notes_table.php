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
            $table->string('vendor_credit_note_ref', 100)->nullable()->after('credit_note_number');
            $table->decimal('utilized_amount', 15, 2)->default(0.00)->after('applied_to_invoice_amount');
            $table->string('accounting_version', 20)->default('v1')->after('status');
            $table->string('submission_token', 100)->nullable()->unique()->after('public_uuid');

            $table->index(['supplier_id', 'accounting_version', 'status', 'money_effect'], 'idx_pcn_supplier_version_status_effect');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_credit_notes', function (Blueprint $table): void {
            $table->dropIndex('idx_pcn_supplier_version_status_effect');
            $table->dropUnique(['submission_token']);
            $table->dropColumn([
                'vendor_credit_note_ref',
                'utilized_amount',
                'accounting_version',
                'submission_token',
            ]);
        });
    }
};
