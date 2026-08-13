<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cashbook_preset_collection_group_entry_types');
        Schema::dropIfExists('cashbook_preset_collection_groups');

        Schema::create('cashbook_preset_collection_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('preset_id');
            $table->string('name');
            $table->string('code');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['preset_id', 'code'], 'preset_collection_group_unique');
            $table->foreign('preset_id', 'preset_collection_group_preset_fk')
                ->references('id')
                ->on('cashbook_config_presets')
                ->cascadeOnDelete();
        });

        Schema::create('cashbook_preset_collection_group_entry_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_group_id');
            $table->unsignedBigInteger('entry_type_id');
            $table->string('role');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_group_id', 'entry_type_id'], 'collection_group_entry_unique');
            $table->index(['collection_group_id', 'role'], 'collection_group_role_idx');
            $table->foreign('collection_group_id', 'collection_group_entry_group_fk')
                ->references('id')
                ->on('cashbook_preset_collection_groups')
                ->cascadeOnDelete();
            $table->foreign('entry_type_id', 'collection_group_entry_type_fk')
                ->references('id')
                ->on('ledger_entry_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_preset_collection_group_entry_types');
        Schema::dropIfExists('cashbook_preset_collection_groups');
    }
};
