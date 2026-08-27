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
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->string('client_submission_id', 100)->nullable()->unique()->after('public_uuid');
            $table->string('submission_payload_hash', 64)->nullable()->after('client_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_received', function (Blueprint $table): void {
            $table->dropUnique(['client_submission_id']);
            $table->dropColumn(['client_submission_id', 'submission_payload_hash']);
        });
    }
};
