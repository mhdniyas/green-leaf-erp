<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchasing\ShopPurchaserDailyVerification;
use App\Models\Shop;
use App\Services\Cashbook\DailyLedgerService;
use App\Services\Purchasing\ShopPurchaserDailyVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AdminShopPurchasingVerificationController extends Controller
{
    public function __construct(
        private readonly ShopPurchaserDailyVerificationService $verificationService,
        private readonly DailyLedgerService $dailyLedgerService,
    ) {}

    public function index(Request $request): View
    {
        $shops = Shop::query()->where('is_active', true)->where('shop_purchasing_enabled', true)->orderBy('name')->get();
        if ($shops->isEmpty()) {
            $shops = Shop::query()->where('is_active', true)->orderBy('name')->get();
        }

        $selectedShopId = $request->integer('shop_id') ?: ($shops->first()?->id ?? 0);
        /** @var Shop|null $selectedShop */
        $selectedShop = Shop::query()->find($selectedShopId) ?? $shops->first();

        $date = $request->string('date', today()->toDateString())->toString();
        if ($selectedShop) {
            $date = $this->dailyLedgerService->resolveActiveBusinessDate($selectedShop, $request->string('date', '')->toString() ?: null);
        }

        $purchaserRows = $selectedShop ? $this->verificationService->getAdminDailyStatus($selectedShop, $date) : collect();

        return view('admin.purchasing.daily-verifications', [
            'shops' => $shops,
            'selectedShop' => $selectedShop,
            'selectedShopId' => $selectedShopId,
            'date' => $date,
            'purchaserRows' => $purchaserRows,
        ]);
    }

    public function reopen(Request $request, ShopPurchaserDailyVerification $verification): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $user = $request->user();

        try {
            $verification = $this->verificationService->reopenDay($verification, $user, $validated['reason']);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchasing day for {$verification->purchaser?->name} on {$verification->business_date->toDateString()} has been reopened.",
                    'verification' => $verification,
                ]);
            }

            return back()->with('success', "Purchasing day for {$verification->purchaser?->name} on {$verification->business_date->toDateString()} has been reopened.");
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
}
