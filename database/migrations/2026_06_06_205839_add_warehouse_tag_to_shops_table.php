<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('warehouse_tag', 12)
                ->nullable()
                ->after('name')
                ->unique();
        });

        $tagMap = [
            'SHOP_CASIO' => 'A',
            'SHOP_BUDEGERE' => 'B',
            'SHOP_GRANCITY' => 'C',
            'SHOP_ASHIRWAD' => 'D',
            'SHOP_SANA' => 'E',
            'SHOP_BAZARO' => 'F',
            'SHOP_SANA_JP' => 'G',
            'SHOP_VARTHUR' => 'H',
            'SHOP_GM' => 'I',
            'SHOP_HSR' => 'J',
            'SHOP_BEGUR' => 'K',
            'SHOP_JINDAL' => 'L',
            'SHOP_CARRY' => 'M',
            'SHOP_FORTUNE' => 'N',
            'SHOP_001' => 'Z',
        ];

        foreach ($tagMap as $shopCode => $warehouseTag) {
            DB::table('shops')
                ->where('code', $shopCode)
                ->update(['warehouse_tag' => $warehouseTag]);
        }
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropUnique('shops_warehouse_tag_unique');
            $table->dropColumn('warehouse_tag');
        });
    }
};
