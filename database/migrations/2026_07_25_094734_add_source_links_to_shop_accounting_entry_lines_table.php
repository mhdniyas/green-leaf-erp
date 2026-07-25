<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->string('source_type', 120)->nullable()->after('review_note');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('source_event', 60)->nullable()->after('source_id');

            $table->index(['source_type', 'source_id'], 'shop_accounting_lines_source_index');
            $table->unique(['source_type', 'source_id', 'source_event'], 'shop_accounting_lines_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shop_accounting_entry_lines', function (Blueprint $table): void {
            $table->dropUnique('shop_accounting_lines_source_unique');
            $table->dropIndex('shop_accounting_lines_source_index');
            $table->dropColumn(['source_type', 'source_id', 'source_event']);
        });
    }
};
