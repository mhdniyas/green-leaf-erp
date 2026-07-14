<?php

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
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->string('source_type', 120)->nullable()->after('reference');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->string('source_event', 60)->nullable()->after('source_id');
            $table->unique(['source_type', 'source_id', 'source_event'], 'journal_entries_source_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropUnique('journal_entries_source_identity_unique');
            $table->dropColumn(['source_type', 'source_id', 'source_event']);
        });
    }
};
