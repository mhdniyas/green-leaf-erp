<?php

declare(strict_types=1);

use App\Models\Cashbook\ShopLedgerProfile;
use App\Services\Cashbook\ShopSettlementService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(ShopSettlementService::class);
        $profiles = ShopLedgerProfile::query()->get();

        foreach ($profiles as $profile) {
            $service->ensureDefaults($profile);
        }
    }

    public function down(): void
    {
        // Defaults can remain configured
    }
};
