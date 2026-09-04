<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_ledger_header_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('type'); // 'income' or 'expense'
            $table->integer('display_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['shop_id', 'type']);
        });

        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->foreignId('header_group_id')
                ->nullable()
                ->after('entry_type_id')
                ->constrained('shop_ledger_header_groups')
                ->nullOnDelete();

            $table->integer('header_display_order')->default(0)->after('header_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_ledger_entry_settings', function (Blueprint $table) {
            $table->dropForeign(['header_group_id']);
            $table->dropColumn(['header_group_id', 'header_display_order']);
        });

        Schema::dropIfExists('shop_ledger_header_groups');
    }
};
