<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Actions\Purchasing\CancelPurchaseInvoiceAction;
use App\Enums\Purchasing\ShopPurchaserDailyVerificationStatus;
use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Product;
use App\Models\Purchasing\ShopPurchaserDailyVerification;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Services\Purchasing\ShopPurchaserDailyVerificationService;
use App\Services\Purchasing\ShopPurchaseService;
use Database\Seeders\Cashbook\LedgerEntryTypeSeeder;
use Database\Seeders\Cashbook\ShopConfigPresetSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ShopPurchaserDailyVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $purchaserA;

    private User $purchaserB;

    private User $secondVerifier;

    private User $admin;

    private Shop $shop;

    private Supplier $supplier;

    private Product $product;

    private ShopPurchaseService $purchaseService;

    private ShopPurchaserDailyVerificationService $verificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerEntryTypeSeeder::class);
        $this->seed(ShopConfigPresetSeeder::class);

        $this->shop = Shop::query()->create([
            'name' => 'City Center Shop',
            'code' => 'CITY_01',
            'shop_purchasing_enabled' => true,
            'accounting_enabled' => true,
            'accounting_mode' => 'owned',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->purchaserA = User::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Purchaser A']);
        $this->purchaserA->assignRole('shop');
        $this->purchaserA->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->purchaserB = User::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Purchaser B']);
        $this->purchaserB->assignRole('shop');
        $this->purchaserB->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->secondVerifier = User::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Shop Manager']);
        $this->secondVerifier->assignRole('shop');
        $this->secondVerifier->ownedShopAssignments()->create(['shop_id' => $this->shop->id]);

        $this->admin = User::factory()->create(['name' => 'Super Admin']);
        $this->admin->assignRole('admin');

        $this->supplier = Supplier::factory()->create(['name' => 'Supreme Produce']);
        $this->product = Product::factory()->create(['name' => 'Red Onions', 'unit' => 'kg']);

        $cashPurchaseType = LedgerEntryType::firstOrCreate(
            ['code' => 'cash_purchase'],
            ['name' => 'Cash Purchase', 'category' => 'expense']
        );

        ShopLedgerEntrySetting::firstOrCreate(
            [
                'shop_id' => $this->shop->id,
                'entry_type_id' => $cashPurchaseType->id,
            ],
            [
                'enabled' => true,
                'display_name' => 'Cash Purchase',
                'default_funding_source' => 'sales',
                'edit_policy' => 'past_days_allowed',
                'include_in_expense' => true,
                'effective_from' => '2026-01-01',
            ]
        );

        $this->purchaseService = app(ShopPurchaseService::class);
        $this->verificationService = app(ShopPurchaserDailyVerificationService::class);
    }

    public function test_1_correct_purchaser_day_scope_isolation(): void
    {
        $date = '2026-09-14';

        // Purchaser A records a purchase
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Purchaser B records a purchase
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 20, 'unit' => 'kg', 'unit_price' => 25],
            ],
        ], $this->purchaserB);

        $evalA = $this->verificationService->evaluateBills($this->shop, $date, $this->purchaserA);
        $evalB = $this->verificationService->evaluateBills($this->shop, $date, $this->purchaserB);

        $this->assertEquals(1, $evalA['total_bills']);
        $this->assertEquals(300.00, $evalA['cash_total']);
        $this->assertEquals(0.00, $evalA['credit_total']);

        $this->assertEquals(1, $evalB['total_bills']);
        $this->assertEquals(0.00, $evalB['cash_total']);
        $this->assertEquals(500.00, $evalB['credit_total']);
    }

    public function test_2_incomplete_bill_blocks_user_verification(): void
    {
        $date = '2026-09-14';

        // Create a bill with invalid line total (amount mismatch)
        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Tamper with amount to simulate calculation failure
        $invoice->update(['amount' => 999.00]);

        $eval = $this->verificationService->evaluateBills($this->shop, $date, $this->purchaserA);
        $this->assertFalse($eval['all_clear']);
        $this->assertEquals(1, $eval['incomplete_bills_count']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot verify day');
        $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
    }

    public function test_3_complete_bills_allow_user_verification(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);

        $this->assertEquals(ShopPurchaserDailyVerificationStatus::UserVerified, $verification->status);
        $this->assertEquals($this->purchaserA->id, $verification->verified_by);
        $this->assertNotNull($verification->verified_at);
    }

    public function test_4_carry_forward_allows_user_verification(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Tamper invoice to make it incomplete
        $invoice->update(['amount' => 999.00]);

        // Formally carry forward the incomplete bill
        $this->verificationService->carryForwardBill($invoice, $this->purchaserA, 'Awaiting price confirmation from vendor');

        $eval = $this->verificationService->evaluateBills($this->shop, $date, $this->purchaserA);
        $this->assertTrue($eval['all_clear']);
        $this->assertEquals(1, $eval['carried_forward_count']);

        // Verification now succeeds
        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::UserVerified, $verification->status);
    }

    public function test_5_carry_forward_preserves_original_business_date(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 40],
            ],
        ], $this->purchaserA);

        $this->verificationService->carryForwardBill($invoice, $this->purchaserA, 'Vendor invoice pending physical copy');

        $fresh = $invoice->fresh();
        $this->assertTrue($fresh->is_carried_forward);
        $this->assertEquals('2026-09-14', $fresh->original_business_date->toDateString());
        $this->assertEquals('2026-09-14', $fresh->purchaserCart->business_date->toDateString());
        $this->assertEquals('Vendor invoice pending physical copy', $fresh->carry_forward_reason);
        $this->assertEquals($this->purchaserA->id, $fresh->carried_forward_by);
        $this->assertNotNull($fresh->carried_forward_at);
    }

    public function test_6_verification_snapshot_saved_accurately(): void
    {
        $date = '2026-09-14';

        // 1 Cash bill = 300
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // 1 Credit bill = 400
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 40],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);

        $snapshot = $verification->summary_snapshot;
        $this->assertIsArray($snapshot);
        $this->assertEquals(2, $snapshot['total_bills']);
        $this->assertEquals(300.00, $snapshot['cash_total']);
        $this->assertEquals(400.00, $snapshot['credit_total']);
        $this->assertEquals(700.00, $snapshot['total_amount']);

        $checklist = $verification->checklist_snapshot;
        $this->assertIsArray($checklist);
        $this->assertTrue($checklist['vendor_validated']);
        $this->assertTrue($checklist['totals_validated']);
    }

    public function test_7_same_purchaser_cannot_second_verify_own_day(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchaser cannot second-verify their own');
        $this->verificationService->secondVerifyDay($verification, $this->purchaserA);
    }

    public function test_8_authorized_second_verifier_succeeds(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $verified = $this->verificationService->secondVerifyDay($verification, $this->secondVerifier);

        $this->assertEquals(ShopPurchaserDailyVerificationStatus::SecondVerified, $verified->status);
        $this->assertEquals($this->secondVerifier->id, $verified->second_verified_by);
        $this->assertNotNull($verified->second_verified_at);
    }

    public function test_9_finalization_locks_purchase_creation(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $verification = $this->verificationService->secondVerifyDay($verification, $this->secondVerifier);
        $finalized = $this->verificationService->finalizeDay($verification, $this->admin);

        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Finalized, $finalized->status);
        $this->assertEquals($this->admin->id, $finalized->finalized_by);

        // Attempting to record a new purchase for the finalized scope throws RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has been finalized for this purchaser');
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);
    }

    public function test_10_finalization_locks_purchase_mutations_and_carry_forward(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $verification = $this->verificationService->secondVerifyDay($verification, $this->secondVerifier);
        $this->verificationService->finalizeDay($verification, $this->admin);

        // Carry forward should be blocked on finalized scope
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has been finalized for this purchaser');
        $this->verificationService->carryForwardBill($invoice, $this->purchaserA, 'Attempted carry forward after finalization');
    }

    public function test_11_finalization_isolation_other_purchasers_remain_unlocked(): void
    {
        $date = '2026-09-14';

        // Purchaser A purchases & finalizes
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $vA = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $vA = $this->verificationService->secondVerifyDay($vA, $this->secondVerifier);
        $this->verificationService->finalizeDay($vA, $this->admin);

        // Purchaser B can still record purchases on the same shop & date!
        $invB = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 50],
            ],
        ], $this->purchaserB);

        $this->assertNotNull($invB);
        $this->assertEquals(250.00, (float) $invB->amount);
    }

    public function test_12_next_business_day_blocked_when_previous_day_unfinalized(): void
    {
        $day1 = '2026-09-14';
        $day2 = '2026-09-15';

        // Purchaser A records on Day 1
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $day1,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Day 1 is not yet finalized. Attempting to record on Day 2 throws RuntimeException
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Previous purchasing day ({$day1}) is not finalized");
        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $day2,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);
    }

    public function test_13_carried_forward_bills_do_not_block_finalization_or_progression(): void
    {
        $day1 = '2026-09-14';
        $day2 = '2026-09-15';

        // Record bill on Day 1
        $inv = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $day1,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Carry forward the bill
        $this->verificationService->carryForwardBill($inv, $this->purchaserA, 'Carried forward to next day');

        // Verification & finalization of Day 1 succeeds
        $v = $this->verificationService->verifyMyDay($this->shop, $day1, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $this->verificationService->finalizeDay($v, $this->admin);

        // Now Purchaser A can proceed to Day 2
        $inv2 = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $day2,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 15, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $this->assertNotNull($inv2);
        $this->assertEquals(450.00, (float) $inv2->amount);
    }

    public function test_14_admin_reopen_requires_reason(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $v = $this->verificationService->finalizeDay($v, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Reopen reason is required');
        $this->verificationService->reopenDay($v, $this->admin, '   ');
    }

    public function test_15_reopened_day_allows_corrections_and_requires_full_verification_cycle(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $v = $this->verificationService->finalizeDay($v, $this->admin);

        // Admin reopens with reason
        $reopened = $this->verificationService->reopenDay($v, $this->admin, 'Missing 1 crate of tomatoes in initial tally');
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Reopened, $reopened->status);
        $this->assertEquals('Missing 1 crate of tomatoes in initial tally', $reopened->reopen_reason);
        $this->assertEquals($this->admin->id, $reopened->reopened_by);
        $this->assertNotNull($reopened->reopened_at);

        // Now purchaser can record the missing purchase bill
        $inv2 = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);
        $this->assertNotNull($inv2);

        // Reopened day must go through the verification lifecycle again
        $v2 = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::UserVerified, $v2->status);

        $v2 = $this->verificationService->secondVerifyDay($v2, $this->secondVerifier);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::SecondVerified, $v2->status);

        $v2 = $this->verificationService->finalizeDay($v2, $this->admin);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Finalized, $v2->status);
    }

    public function test_16_admin_daily_status_overview(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $statusRows = $this->verificationService->getAdminDailyStatus($this->shop, $date);

        $this->assertCount(1, $statusRows);
        $row = $statusRows->first();
        $this->assertEquals($this->purchaserA->id, $row['purchaser']->id);
        $this->assertEquals(1, $row['total_bills']);
        $this->assertEquals(300.00, $row['cash_total']);
        $this->assertEquals(0.00, $row['credit_total']);
        $this->assertEquals(0, $row['pending_count']);
    }

    public function test_17_controller_endpoints_and_web_routes(): void
    {
        $date = '2026-09-14';

        // 1. Purchaser visits Daily Verification page
        $response = $this->actingAs($this->purchaserA)->get(route('shop-owner.purchasing.verification', ['date' => $date]));
        $response->assertOk();

        // 2. Purchaser records a purchase
        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // 3. User Verify POST
        $verifyRes = $this->actingAs($this->purchaserA)->post(route('shop-owner.purchasing.verification.verify'), [
            'business_date' => $date,
        ]);
        $verifyRes->assertRedirect();
        $verifyRes->assertSessionHas('success');

        $verification = ShopPurchaserDailyVerification::query()
            ->where('shop_id', $this->shop->id)
            ->where('business_date', $date)
            ->where('purchaser_user_id', $this->purchaserA->id)
            ->firstOrFail();

        // 4. Second verify by Shop Manager
        $secondRes = $this->actingAs($this->secondVerifier)->post(route('shop-owner.purchasing.verification.second-verify'), [
            'verification_id' => $verification->id,
        ]);
        $secondRes->assertRedirect();
        $secondRes->assertSessionHas('success');

        // 5. Finalize by Second Verifier / Manager
        $finalizeRes = $this->actingAs($this->secondVerifier)->post(route('shop-owner.purchasing.verification.finalize'), [
            'verification_id' => $verification->id,
        ]);
        $finalizeRes->assertRedirect();
        $finalizeRes->assertSessionHas('success');

        // 6. Admin visits admin status page
        $adminPageRes = $this->actingAs($this->admin)->get(route('admin.purchasing.daily-verifications.index', [
            'shop_id' => $this->shop->id,
            'date' => $date,
        ]));
        $adminPageRes->assertOk();

        // 7. Admin reopens via POST
        $reopenRes = $this->actingAs($this->admin)->post(route('admin.purchasing.daily-verifications.reopen', [
            'verification' => $verification->id,
        ]), [
            'reason' => 'Vendor requested bill adjustment',
        ]);
        $reopenRes->assertRedirect();
        $reopenRes->assertSessionHas('success');

        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Reopened, $verification->fresh()->status);
    }

    public function test_18_cancellation_cannot_bypass_finalized_lock(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $this->verificationService->finalizeDay($v, $this->admin);

        $cancelAction = app(CancelPurchaseInvoiceAction::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has been finalized for this purchaser. Modifications are locked.');
        $cancelAction->execute($invoice, $this->admin, 'Wrong bill entered');
    }

    public function test_19_payment_update_cannot_bypass_finalized_lock(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Credit',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $this->verificationService->finalizeDay($v, $this->admin);

        $invoiceService = app(PurchaseInvoiceService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has been finalized for this purchaser. Modifications are locked.');
        $invoiceService->updatePayment($invoice, [
            'payment_method' => 'Cash',
            'paid_amount' => 300.00,
            'payment_note' => 'Payment settled late',
            'payment_details' => null,
        ]);
    }

    public function test_20_status_and_calculation_fix_cannot_bypass_finalized_lock(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $this->verificationService->finalizeDay($v, $this->admin);

        $invoiceService = app(PurchaseInvoiceService::class);

        try {
            $invoiceService->updateStatus($invoice, 'pending');
            $this->fail('Expected exception for updateStatus on finalized scope was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('has been finalized for this purchaser', $e->getMessage());
        }

        try {
            $invoiceService->fixCalculationError($invoice);
            $this->fail('Expected exception for fixCalculationError on finalized scope was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('has been finalized for this purchaser', $e->getMessage());
        }
    }

    public function test_21_invalid_state_transitions_explicitly_rejected(): void
    {
        $date = '2026-09-14';

        $invoice = $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $verification = $this->verificationService->getOrCreateVerification($this->shop, $date, $this->purchaserA);

        // 1. OPEN -> FINALIZED directly rejected
        try {
            $this->verificationService->finalizeDay($verification, $this->admin);
            $this->fail('Expected OPEN -> FINALIZED transition to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Purchasing day must have second verification completed', $e->getMessage());
        }

        // 2. Perform User Verification (OPEN -> USER_VERIFIED)
        $verification = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::UserVerified, $verification->status);

        // 3. Duplicate USER_VERIFIED -> USER_VERIFIED rejected
        try {
            $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
            $this->fail('Expected duplicate USER_VERIFIED transition to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already been verified by purchaser', $e->getMessage());
        }

        // 4. Same purchaser performing second verification rejected
        try {
            $this->verificationService->secondVerifyDay($verification, $this->purchaserA);
            $this->fail('Expected same purchaser second-verifying to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Purchaser cannot second-verify their own', $e->getMessage());
        }

        // 5. Perform Second Verification (USER_VERIFIED -> SECOND_VERIFIED)
        $verification = $this->verificationService->secondVerifyDay($verification, $this->secondVerifier);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::SecondVerified, $verification->status);

        // 6. SECOND_VERIFIED -> REOPEN directly rejected (must be finalized before reopen)
        try {
            $this->verificationService->reopenDay($verification, $this->admin, 'Premature reopen');
            $this->fail('Expected SECOND_VERIFIED -> REOPEN to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Only finalized purchasing days can be reopened', $e->getMessage());
        }

        // 7. Perform Finalization (SECOND_VERIFIED -> FINALIZED)
        $verification = $this->verificationService->finalizeDay($verification, $this->admin);
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Finalized, $verification->status);

        // 8. FINALIZED -> User Verify without Reopen rejected
        try {
            $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
            $this->fail('Expected FINALIZED -> User Verify to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Purchasing day is finalized. It must be reopened', $e->getMessage());
        }

        // 9. Reopen by admin (FINALIZED -> REOPENED)
        $verification = $this->verificationService->reopenDay($verification, $this->admin, 'Need to add an extra bill');
        $this->assertEquals(ShopPurchaserDailyVerificationStatus::Reopened, $verification->status);

        // 10. REOPENED -> FINALIZED directly without re-verification rejected
        try {
            $this->verificationService->finalizeDay($verification, $this->admin);
            $this->fail('Expected REOPENED -> FINALIZED to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Purchasing day must have second verification completed', $e->getMessage());
        }
    }

    public function test_22_duplicate_finalization_rejected(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        $v = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v = $this->verificationService->secondVerifyDay($v, $this->secondVerifier);
        $finalized = $this->verificationService->finalizeDay($v, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Purchasing day is already finalized.');
        $this->verificationService->finalizeDay($finalized, $this->admin);
    }

    public function test_23_durable_audit_history_survives_reopen_and_refinalize(): void
    {
        $date = '2026-09-14';

        $this->purchaseService->recordPurchase($this->shop, [
            'supplier_id' => $this->supplier->id,
            'business_date' => $date,
            'payment_method' => 'Cash',
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 10, 'unit' => 'kg', 'unit_price' => 30],
            ],
        ], $this->purchaserA);

        // Cycle 1: Verify -> Second Verify -> Finalize
        $v1 = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v2 = $this->verificationService->secondVerifyDay($v1, $this->secondVerifier);
        $v3 = $this->verificationService->finalizeDay($v2, $this->admin);

        // Reopen with reason
        $v4 = $this->verificationService->reopenDay($v3, $this->admin, 'Audit review correction needed');

        // Cycle 2: Verify Again -> Second Verify Again -> Finalize Again
        $v5 = $this->verificationService->verifyMyDay($this->shop, $date, $this->purchaserA);
        $v6 = $this->verificationService->secondVerifyDay($v5, $this->secondVerifier);
        $v7 = $this->verificationService->finalizeDay($v6, $this->admin);

        $activities = Activity::query()
            ->where('subject_type', ShopPurchaserDailyVerification::class)
            ->where('subject_id', $v7->id)
            ->orderBy('id')
            ->get();

        $events = $activities->pluck('event')->filter()->values()->all();

        // Must have recorded all lifecycle events across both cycles
        $this->assertContains('purchaser_verified', $events);
        $this->assertContains('second_verified', $events);
        $this->assertContains('finalized', $events);
        $this->assertContains('reopened', $events);

        $reopenActivity = $activities->firstWhere('event', 'reopened');
        $this->assertNotNull($reopenActivity);
        $this->assertEquals('Audit review correction needed', $reopenActivity->properties['reopen_reason'] ?? null);
        $this->assertEquals($this->admin->id, $reopenActivity->causer_id);
    }
}
