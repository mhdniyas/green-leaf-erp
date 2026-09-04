<?php

use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $shop = Shop::firstOrCreate(
            ['code' => 'AV_REL_TEST'],
            [
                'name' => 'Relation Test Shop',
                'accounting_enabled' => true,
                'accounting_mode' => 'owned',
            ]
        );

        ShopLedgerProfile::firstOrCreate(
            ['shop_id' => $shop->id],
            [
                'code' => $shop->code,
                'name' => $shop->name,
                'slug' => 'relation-test-shop',
                'profile_template' => 'owned_standard',
                'enabled' => true,
                'closing_mode' => 'manual',
            ]
        );
    }

    public function down(): void
    {
        //
    }
};
