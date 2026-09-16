<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cashbook\ShopLedgerProfile;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\Cashbook\CashbookShopSyncService;
use App\Support\ShopOwner\ActiveShopResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashbookVendorController extends Controller
{
    public function __construct(
        private readonly CashbookShopSyncService $shopSync,
        private readonly ActiveShopResolver $activeShopResolver,
    ) {}

    public function index(Request $request, string $shop): View
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);
        $currentShopProfile = $this->resolveShopProfile($shopModel);
        $shopKey = $currentShopProfile?->slug ?: (string) $shopModel->id;

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $query = $shopModel->suppliers()
            ->withPivot('is_active')
            ->withCount(['purchaseInvoices' => fn ($q) => $q->where('shop_id', $shopModel->id)])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->wherePivot('is_active', true))
            ->when($status === 'disabled', fn ($q) => $q->wherePivot('is_active', false))
            ->orderBy('name');

        $shopSuppliers = $query->paginate(25)->withQueryString();

        return view('admin.cashbook.settings.vendors.index', [
            'shop' => $shopModel,
            'currentShop' => $currentShopProfile ?? (object) [
                'shop_id' => $shopModel->id,
                'name' => $shopModel->name,
                'code' => $shopModel->code,
                'slug' => $shopModel->slug ?? (string) $shopModel->id,
            ],
            'shopKey' => $shopKey,
            'shopSuppliers' => $shopSuppliers,
            'search' => $search,
            'status' => $status,
            'totalVendorsCount' => $shopModel->suppliers()->count(),
            'activeVendorsCount' => $shopModel->suppliers()->wherePivot('is_active', true)->count(),
        ]);
    }

    public function link(Request $request, string $shop): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        $supplierId = (int) $validated['supplier_id'];
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->findOrFail($supplierId);

        if ($shopModel->suppliers()->where('supplier_id', $supplierId)->exists()) {
            $shopModel->suppliers()->updateExistingPivot($supplierId, [
                'is_active' => true,
                'credit_approved' => (bool) $supplier->credit_approved,
            ]);
        } else {
            $shopModel->suppliers()->attach($supplierId, [
                'is_active' => true,
                'credit_approved' => (bool) $supplier->credit_approved,
            ]);
        }

        $message = "Vendor '{$supplier->name}' linked to {$shopModel->name} successfully.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function toggleCreationPermission(Request $request, string $shop): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);
        $user = $request->user();
        abort_unless($user && ($user->isMainAdmin() || $user->hasRole('admin')), 403, 'Unauthorized to change vendor creation permissions.');

        $newState = ! $shopModel->isVendorCreationAllowed();
        $shopModel->update(['allow_vendor_creation' => $newState]);

        $statusStr = $newState ? 'enabled' : 'disabled';
        $message = "Shop Owner vendor creation has been {$statusStr} for {$shopModel->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'allow_vendor_creation' => $newState]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function storeNew(Request $request, string $shop): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);
        $user = $request->user();
        $isAdmin = $user && ($user->isMainAdmin() || $user->hasRole('admin'));

        if (! $isAdmin) {
            abort_unless($shopModel->isVendorCreationAllowed(), 403, 'Shop Owner vendor creation is disabled for this shop.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:255'],
            'credit_approved' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $name = trim($validated['name']);
        $mobile = trim((string) ($validated['mobile_number'] ?? ''));
        $creditApproved = $isAdmin ? (bool) ($validated['credit_approved'] ?? true) : false;

        /** @var Supplier $supplier */
        $supplier = Supplier::query()->firstOrCreate(
            ['name' => $name],
            [
                'mobile_number' => $mobile ?: null,
                'contact' => $validated['contact'] ?? null,
                'type' => 'local',
                'category' => 'shop_vendor',
                'credit_approved' => $creditApproved,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($shopModel->suppliers()->where('supplier_id', $supplier->id)->exists()) {
            $shopModel->suppliers()->updateExistingPivot($supplier->id, [
                'is_active' => true,
                'credit_approved' => $isAdmin ? $creditApproved : (bool) ($shopModel->suppliers()->where('supplier_id', $supplier->id)->first()?->pivot?->credit_approved ?? false),
            ]);
        } else {
            $shopModel->suppliers()->attach($supplier->id, [
                'is_active' => true,
                'credit_approved' => $creditApproved,
            ]);
        }

        $freshPivot = $shopModel->suppliers()->where('supplier_id', $supplier->id)->first();
        $finalCreditApproved = (bool) ($freshPivot?->pivot?->credit_approved ?? false);

        $message = "Vendor '{$supplier->name}' created and linked to {$shopModel->name} successfully.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'mobile_number' => $supplier->mobile_number,
                    'contact' => $supplier->contact,
                    'credit_approved' => $finalCreditApproved,
                ],
                'vendor' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'mobile_number' => $supplier->mobile_number,
                    'contact' => $supplier->contact,
                    'credit_approved' => $finalCreditApproved,
                ],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function update(Request $request, string $shop, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);

        abort_unless($shopModel->suppliers()->where('supplier_id', $supplier->id)->exists(), 404, 'Vendor not linked to this shop.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:50'],
            'contact' => ['nullable', 'string', 'max:255'],
            'credit_approved' => ['nullable', 'boolean'],
        ]);

        $creditApproved = (bool) ($validated['credit_approved'] ?? false);

        $supplier->update([
            'name' => trim($validated['name']),
            'mobile_number' => trim((string) ($validated['mobile_number'] ?? '')) ?: null,
            'contact' => trim((string) ($validated['contact'] ?? '')) ?: null,
            'credit_approved' => $creditApproved,
        ]);

        $shopModel->suppliers()->updateExistingPivot($supplier->id, [
            'credit_approved' => $creditApproved,
        ]);

        $message = "Vendor '{$supplier->name}' updated successfully.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'supplier' => $supplier]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function toggleStatus(Request $request, string $shop, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);

        $relation = $shopModel->suppliers()->where('supplier_id', $supplier->id)->first();
        abort_unless($relation, 404, 'Vendor not linked to this shop.');

        $currentStatus = (bool) ($relation->pivot->is_active ?? true);
        $newStatus = ! $currentStatus;

        $shopModel->suppliers()->updateExistingPivot($supplier->id, ['is_active' => $newStatus]);

        $statusText = $newStatus ? 'enabled' : 'disabled';
        $message = "Vendor '{$supplier->name}' is now {$statusText} for {$shopModel->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'is_active' => $newStatus]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function unlink(Request $request, string $shop, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);

        abort_unless($shopModel->suppliers()->where('supplier_id', $supplier->id)->exists(), 404, 'Vendor not linked to this shop.');

        $shopModel->suppliers()->detach($supplier->id);

        $message = "Vendor '{$supplier->name}' unlinked from {$shopModel->name}.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function searchGlobalSuppliers(Request $request, string $shop): JsonResponse
    {
        $shopModel = $this->resolveAuthorizedShop($request, $shop);
        $search = trim((string) $request->input('q', ''));

        $linkedSupplierIds = $shopModel->suppliers()->pluck('suppliers.id')->all();

        $suppliers = Supplier::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sq) use ($search): void {
                    $sq->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_number', 'like', "%{$search}%")
                        ->orWhere('contact', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'mobile_number', 'contact', 'credit_approved'])
            ->map(fn (Supplier $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'mobile_number' => $s->mobile_number,
                'contact' => $s->contact,
                'credit_approved' => (bool) $s->credit_approved,
                'is_already_linked' => in_array($s->id, $linkedSupplierIds, true),
            ]);

        return response()->json(['suppliers' => $suppliers]);
    }

    public function shopOwnerIndex(Request $request): RedirectResponse
    {
        $authorizedShops = $this->activeShopResolver->authorizedShops($request->user());
        /** @var Shop|null $shop */
        $shop = $authorizedShops->isNotEmpty()
            ? $this->activeShopResolver->resolve($request)
            : $request->user()?->shop;

        abort_unless($shop instanceof Shop, 403, 'No shop assigned.');

        $profile = $this->resolveShopProfile($shop);
        $shopKey = $profile?->slug ?: (string) $shop->id;

        return redirect()->route('admin.cashbook.settings.shop.vendors.index', ['shop' => $shopKey]);
    }

    private function resolveAuthorizedShop(Request $request, string|int $shop): Shop
    {
        $profiles = $this->shopSync->syncAndGetProfiles();

        $profile = null;
        if (is_numeric($shop)) {
            $profile = $profiles->first(fn (ShopLedgerProfile $p): bool => (int) $p->shop_id === (int) $shop);
        }

        if (! $profile) {
            $profile = $profiles->first(fn (ShopLedgerProfile $p): bool => in_array((string) $shop, [(string) $p->shop_id, (string) $p->slug, (string) $p->uuid, (string) $p->code], true));
        }

        $shopModel = null;
        if ($profile) {
            $shopModel = Shop::query()->find($profile->shop_id);
        }

        if (! $shopModel) {
            $shopModel = is_numeric($shop)
                ? Shop::query()->find((int) $shop)
                : Shop::query()->where('code', (string) $shop)->orWhere('public_uuid', (string) $shop)->first();
        }

        abort_unless($shopModel instanceof Shop, 404, 'Shop not found.');

        $user = $request->user();
        abort_unless($user, 401);

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return $shopModel;
        }

        $authorizedShops = $this->activeShopResolver->authorizedShops($user);
        $isAuthorized = $authorizedShops->contains('id', $shopModel->id)
            || (int) $user->shop_id === (int) $shopModel->id
            || $user->ownedShopAssignments()->where('shop_id', $shopModel->id)->exists();

        abort_unless($isAuthorized, 403, 'Unauthorized access to this shop.');

        return $shopModel;
    }

    private function resolveShopProfile(Shop $shop): ?ShopLedgerProfile
    {
        $profiles = $this->shopSync->syncAndGetProfiles();

        return $profiles->first(fn (ShopLedgerProfile $p): bool => (int) $p->shop_id === (int) $shop->id);
    }
}
