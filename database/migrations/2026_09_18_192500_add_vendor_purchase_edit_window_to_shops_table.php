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
        Schema::table('shops', function (Blueprint $table): void {
            if (! Schema::hasColumn('shops', 'vendor_purchase_edit_window_value')) {
                $table->unsignedSmallInteger('vendor_purchase_edit_window_value')->nullable()->after('allow_vendor_creation');
            }
            if (! Schema::hasColumn('shops', 'vendor_purchase_edit_window_unit')) {
                $table->string('vendor_purchase_edit_window_unit', 10)->nullable()->after('vendor_purchase_edit_window_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            if (Schema::hasColumn('shops', 'vendor_purchase_edit_window_unit')) {
                $table->dropColumn('vendor_purchase_edit_window_unit');
            }
            if (Schema::hasColumn('shops', 'vendor_purchase_edit_window_value')) {
                $table->dropColumn('vendor_purchase_edit_window_value');
            }
        });
    }
};
