<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('shop_ledger_collection_group_entry_types');
        Schema::dropIfExists('shop_ledger_collection_groups');

        Schema::create('shop_ledger_collection_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('name');
            $table->string('code');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'code'], 'shop_collection_group_unique');
            $table->index(['shop_id', 'enabled'], 'shop_collection_group_enabled_idx');
        });

        Schema::create('shop_ledger_collection_group_entry_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_group_id');
            $table->unsignedBigInteger('entry_type_id');
            $table->string('role');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_group_id', 'entry_type_id'], 'shop_collection_group_entry_unique');
            $table->foreign('collection_group_id', 'shop_collection_group_entry_group_fk')
                ->references('id')
                ->on('shop_ledger_collection_groups')
                ->cascadeOnDelete();
            $table->foreign('entry_type_id', 'shop_collection_group_entry_type_fk')
                ->references('id')
                ->on('ledger_entry_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_ledger_collection_group_entry_types');
        Schema::dropIfExists('shop_ledger_collection_groups');
    }
};
