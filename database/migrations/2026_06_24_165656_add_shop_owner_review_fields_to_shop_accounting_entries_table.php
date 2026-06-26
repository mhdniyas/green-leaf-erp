<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('submitted_by')->nullable()->after('updated_by');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('submitted_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('admin_note')->nullable()->after('reviewed_at');
            $table->text('shop_reply_note')->nullable()->after('admin_note');

            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'submitted_by',
                'submitted_at',
                'reviewed_by',
                'reviewed_at',
                'admin_note',
                'shop_reply_note',
            ]);
        });
    }
};
