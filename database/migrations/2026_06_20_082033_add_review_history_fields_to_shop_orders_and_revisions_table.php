<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('manager_note')->nullable()->after('reviewed_at');
        });

        Schema::table('shop_order_revisions', function (Blueprint $table) {
            $table->text('manager_note')->nullable()->after('reviewed_at');
        });

        Schema::table('shop_order_revision_items', function (Blueprint $table) {
            $table->decimal('final_approved_qty', 10, 2)->nullable()->after('delta_qty');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_revision_items', function (Blueprint $table) {
            $table->dropColumn('final_approved_qty');
        });

        Schema::table('shop_order_revisions', function (Blueprint $table) {
            $table->dropColumn('manager_note');
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn('manager_note');
            $table->dropColumn('reviewed_at');
            $table->dropConstrainedForeignId('reviewed_by');
        });
    }
};
