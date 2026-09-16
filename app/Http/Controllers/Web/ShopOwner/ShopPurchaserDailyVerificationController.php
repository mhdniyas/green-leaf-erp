<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\Purchasing\ShopPurchaserDailyVerification;
use App\Models\Shop;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Purchasing\ShopPurchaserDailyVerificationService;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ShopPurchaserDailyVerificationController extends Controller
{
    public function __construct(
        private readonly ShopPurchaserDailyVerificationService $verificationService,
        private readonly DailyLedgerService $dailyLedgerService,
        private readonly ActiveShopResolver $activeShopResolver,
    ) {}

    public function show(Request $request): View
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $requestedDate = $request->string('date', '')->toString();
        $businessDate = $this->dailyLedgerService->resolveActiveBusinessDate($shop, $requestedDate ?: null);
        $user = $request->user();

        $data = $this->verificationService->evaluateBills($shop, $businessDate, $user);

        return view('shop-owner.purchasing.daily-verification', [
            'shop' => $shop,
            'activeShop' => $shop,
            'businessDate' => $businessDate,
            'purchaser' => $user,
            'data' => $data,
            'verification' => $data['verification'],
        ]);
    }

    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $validated = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();

        try {
            $verification = $this->verificationService->verifyMyDay($shop, $validated['business_date'], $user);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchasing day successfully verified.',
                    'verification' => $verification,
                ]);
            }

            return back()->with('success', 'Purchasing day successfully verified. Ready for second verification.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function secondVerify(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $validated = $request->validate([
            'verification_id' => ['required', 'integer', 'exists:shop_purchaser_daily_verifications,id'],
        ]);

        /** @var ShopPurchaserDailyVerification $verification */
        $verification = ShopPurchaserDailyVerification::query()->findOrFail($validated['verification_id']);

        if ((int) $verification->shop_id !== (int) $shop->id) {
            abort(403, 'Unauthorized shop verification record.');
        }

        $user = $request->user();

        try {
            $verification = $this->verificationService->secondVerifyDay($verification, $user);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Second verification completed successfully.',
                    'verification' => $verification,
                ]);
            }

            return back()->with('success', 'Second verification completed successfully.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function finalize(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $validated = $request->validate([
            'verification_id' => ['required', 'integer', 'exists:shop_purchaser_daily_verifications,id'],
        ]);

        /** @var ShopPurchaserDailyVerification $verification */
        $verification = ShopPurchaserDailyVerification::query()->findOrFail($validated['verification_id']);

        if ((int) $verification->shop_id !== (int) $shop->id) {
            abort(403, 'Unauthorized shop verification record.');
        }

        $user = $request->user();

        try {
            $verification = $this->verificationService->finalizeDay($verification, $user);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Purchaser day finalized and locked successfully.',
                    'verification' => $verification,
                ]);
            }

            return back()->with('success', 'Purchaser day finalized and locked successfully.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    public function carryForward(Request $request): RedirectResponse|JsonResponse
    {
        $shop = $this->resolveShop($request);
        $this->ensurePurchasingEnabled($shop);

        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        /** @var PurchaseInvoice $invoice */
        $invoice = PurchaseInvoice::query()->findOrFail($validated['invoice_id']);

        $invoiceShopId = (int) ($invoice->shop_id ?: $invoice->purchaserCart?->destination_shop_id);
        if ($invoiceShopId !== (int) $shop->id) {
            abort(403, 'Unauthorized invoice.');
        }

        $user = $request->user();

        try {
            $invoice = $this->verificationService->carryForwardBill($invoice, $user, $validated['reason']);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Bill {$invoice->invoice_number} marked as carried forward.",
                    'invoice' => $invoice,
                ]);
            }

            return back()->with('success', "Bill {$invoice->invoice_number} marked as carried forward.");
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    private function resolveShop(Request $request): Shop
    {
        $authorizedShops = $this->activeShopResolver->authorizedShops($request->user());
        if ($authorizedShops->isNotEmpty()) {
            return $this->activeShopResolver->resolve($request);
        }

        /** @var Shop $shop */
        $shop = $request->user()?->shop;
        abort_unless($shop instanceof Shop, 403, 'No shop assigned.');

        return $shop;
    }

    private function ensurePurchasingEnabled(Shop $shop): void
    {
        abort_unless($shop->isPurchasingEnabled(), 403, 'Shop Purchasing is not enabled for this shop.');
    }
}
