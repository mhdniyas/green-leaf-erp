<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->string('entry_type', 30)->default('daily')->after('business_date')->index();
            $table->string('daily_entry_key', 120)->nullable()->after('entry_type')->unique('shop_accounting_entries_daily_key_unique');
        });

        $entries = DB::table('shop_accounting_entries')
            ->select('id', 'shop_id', 'business_date', 'status')
            ->orderBy('shop_id')
            ->orderBy('business_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($entry): string => $entry->shop_id.'|'.$entry->business_date);

        foreach ($entries as $dayEntries) {
            $dailyEntry = $dayEntries->firstWhere('status', 'approved')
                ?? $dayEntries->firstWhere('status', 'submitted')
                ?? $dayEntries->firstWhere('status', 'recheck_required')
                ?? $dayEntries->firstWhere('status', 'draft')
                ?? $dayEntries->first();

            foreach ($dayEntries as $entry) {
                $isDaily = (int) $entry->id === (int) $dailyEntry->id;

                DB::table('shop_accounting_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'entry_type' => $isDaily ? 'daily' : 'adjustment',
                        'daily_entry_key' => $isDaily ? $this->dailyEntryKey((int) $entry->shop_id, (string) $entry->business_date) : null,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('shop_accounting_entries', function (Blueprint $table): void {
            $table->dropUnique('shop_accounting_entries_daily_key_unique');
            $table->dropIndex('shop_accounting_entries_entry_type_index');
            $table->dropColumn(['entry_type', 'daily_entry_key']);
        });
    }

    private function dailyEntryKey(int $shopId, string $businessDate): string
    {
        return "shop:{$shopId}:date:{$businessDate}:daily";
    }
};
