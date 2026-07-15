<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ShopInvoice;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\ShopInvoices\ShopInvoiceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class JulyFourteenDailySalesSeeder extends Seeder
{
    private const BUSINESS_DATE = '2026-07-14';

    public function run(ShopInvoiceService $shopInvoiceService): void
    {
        $this->call([
            ChartOfAccountsSeeder::class,
            JulyFourteenShopOwnerOrderSeeder::class,
        ]);

        $admin = $this->ensureAdminUser();

        ShopOrder::query()
            ->with(['items', 'invoice.items'])
            ->whereDate('business_date', self::BUSINESS_DATE)
            ->where('order_number', 'like', 'RQ-SHOP-20260714-%')
            ->orderBy('order_number')
            ->get()
            ->each(function (ShopOrder $order, int $index) use ($shopInvoiceService, $admin): void {
                $order->update([
                    'state' => 'approved',
                    'delivery_status' => 'ready_for_dispatch',
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'manager_note' => 'Seeded July 14 daily sales approval.',
                    'is_allocation_completed' => true,
                ]);

                $deliveredQuantities = $order->items
                    ->mapWithKeys(fn ($item): array => [$item->id => (float) $item->approved_qty])
                    ->all();

                $invoice = $shopInvoiceService->applyDeliveryCheckin(
                    $order->fresh(['items', 'invoice.items']),
                    $deliveredQuantities,
                    (int) $admin->id,
                    'Seeded full delivery for July 14 daily sales.',
                );

                $this->applyPaymentState($shopInvoiceService, $invoice, $index, (int) $admin->id);
            });

        $this->command?->info('Seeded July 14 daily sales invoices for shop-owner checking.');
    }

    private function ensureAdminUser(): User
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'daily.sales.admin@greenleaf.com'],
            [
                'name' => 'Daily Sales Admin',
                'password' => Hash::make('Admin12345'),
                'email_verified_at' => now(),
                'registration_status' => 'approved',
                'approved_at' => now(),
            ],
        );

        if (Role::query()->where('name', 'admin')->exists()) {
            $admin->syncRoles(['admin']);
        }

        return $admin;
    }

    private function applyPaymentState(ShopInvoiceService $shopInvoiceService, ShopInvoice $invoice, int $index, int $userId): void
    {
        $invoice->refresh();
        $finalTotal = round((float) $invoice->final_total, 2);

        $paidAmount = match ($index) {
            2 => $finalTotal,
            3 => round($finalTotal / 2, 2),
            default => 0.00,
        };

        $shopInvoiceService->approvePayment($invoice, [
            'discount_total' => 0,
            'paid_amount' => $paidAmount,
            'payment_note' => match ($index) {
                2 => 'Seeded full payment for daily sales demo.',
                3 => 'Seeded partial payment for daily sales demo.',
                default => 'Seeded unpaid daily sales invoice.',
            },
        ], $userId);
    }
}
