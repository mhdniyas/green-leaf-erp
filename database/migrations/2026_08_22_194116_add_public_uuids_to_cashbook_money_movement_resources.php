<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
        });
        Schema::table('cashbook_company_accounts', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
        });

        DB::table('shops')->orderBy('id')->each(fn (object $shop) => DB::table('shops')->where('id', $shop->id)->update(['public_uuid' => (string) Str::uuid()]));
        DB::table('cashbook_company_accounts')->orderBy('id')->each(fn (object $account) => DB::table('cashbook_company_accounts')->where('id', $account->id)->update(['public_uuid' => (string) Str::uuid()]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cashbook_company_accounts', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropUnique(['public_uuid']);
            $table->dropColumn('public_uuid');
        });
    }
};
