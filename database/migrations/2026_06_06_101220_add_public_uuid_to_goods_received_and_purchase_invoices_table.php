<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_received', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable()->after('id');
        });

        DB::table('goods_received')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                DB::table('goods_received')
                    ->where('id', $record->id)
                    ->update(['public_uuid' => (string) Str::uuid()]);
            });

        DB::table('purchase_invoices')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                DB::table('purchase_invoices')
                    ->where('id', $record->id)
                    ->update(['public_uuid' => (string) Str::uuid()]);
            });

        Schema::table('goods_received', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->uuid('public_uuid')->nullable(false)->change();
            $table->unique('public_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });

        Schema::table('goods_received', function (Blueprint $table) {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
