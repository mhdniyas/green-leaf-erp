<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\ShopAccountingEntry;
use App\Models\User;
use App\Services\Finance\OwnedShopAccountingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ShopAccountingPeriodClosureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_closed_period_blocks_owned_shop_accounting_entry_edits(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create();

        app(OwnedShopAccountingService::class)->closePeriod(
            shop: $shop,
            periodStart: Carbon::parse('2026-07-01'),
            periodEnd: Carbon::parse('2026-07-31'),
            userId: $user->id,
            notes: 'Manual month close',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This accounting period is closed.');

        app(OwnedShopAccountingService::class)->saveEntry($shop, [
            'business_date' => '2026-07-15',
            'status' => 'draft',
            'lines' => [],
        ], $user->id);
    }

    public function test_period_with_pending_entries_cannot_be_closed(): void
    {
        $shop = Shop::factory()->create([
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
        ]);
        $user = User::factory()->create();

        ShopAccountingEntry::query()->create([
            'shop_id' => $shop->id,
            'business_date' => '2026-07-15',
            'status' => 'submitted',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolve draft, pending, or recheck accounting entries before closing this period.');

        app(OwnedShopAccountingService::class)->closePeriod(
            shop: $shop,
            periodStart: Carbon::parse('2026-07-01'),
            periodEnd: Carbon::parse('2026-07-31'),
            userId: $user->id,
        );
    }
}
