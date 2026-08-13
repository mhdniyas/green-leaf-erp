<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_preset_collection_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preset_id')->constrained('cashbook_config_presets')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['preset_id', 'code'], 'preset_collection_group_unique');
        });

        Schema::create('cashbook_preset_collection_group_entry_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_group_id')->constrained('cashbook_preset_collection_groups')->cascadeOnDelete();
            $table->foreignId('entry_type_id')->constrained('ledger_entry_types')->cascadeOnDelete();
            $table->string('role');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_group_id', 'entry_type_id'], 'collection_group_entry_unique');
            $table->index(['collection_group_id', 'role'], 'collection_group_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_preset_collection_group_entry_types');
        Schema::dropIfExists('cashbook_preset_collection_groups');
    }
};
