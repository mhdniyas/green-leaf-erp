<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_settlements', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->after('id');
        });

        DB::table('vendor_settlements')->orderBy('id')->each(function (object $settlement): void {
            DB::table('vendor_settlements')->where('id', $settlement->id)->update([
                'public_uuid' => (string) Str::uuid(),
            ]);
        });

        Schema::table('vendor_settlements', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_settlements', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
