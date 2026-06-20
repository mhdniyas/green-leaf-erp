<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShopPreset;
use App\Models\ShopPresetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopPresetController extends Controller
{
    /**
     * Display a listing of the presets.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user->shop_id) {
            abort(403, 'User is not associated with any shop.');
        }

        $presets = ShopPreset::where('shop_id', $user->shop_id)
            ->with(['items.product', 'creator'])
            ->orderBy('name')
            ->get();

        $staplesSkus = ['1', '2', '13', '15', '101', '104'];
        $favoriteProducts = Product::whereIn('sku', $staplesSkus)->ordered()->get();

        return view('requisitions.presets.index', compact('presets', 'favoriteProducts'));
    }

    /**
     * Show the form for creating a new preset.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        if (! $user->shop_id) {
            abort(403, 'User is not associated with any shop.');
        }

        $products = Product::where('is_active', true)
            ->ordered()
            ->get();

        $prefilledProducts = collect();
        if ($request->has('copy_favorites')) {
            $staplesSkus = ['1', '2', '13', '15', '101', '104'];
            $prefilledProducts = Product::whereIn('sku', $staplesSkus)->ordered()->get();
        }

        return view('requisitions.presets.create', compact('products', 'prefilledProducts'));
    }

    /**
     * Store a newly created preset in storage.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user->shop_id) {
            abort(403, 'User is not associated with any shop.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        $preset = DB::transaction(function () use ($user, $request) {
            $preset = ShopPreset::create([
                'shop_id' => $user->shop_id,
                'name' => $request->input('name'),
                'created_by' => $user->id,
            ]);

            foreach ($request->input('items') as $item) {
                ShopPresetItem::create([
                    'shop_preset_id' => $preset->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                ]);
            }

            return $preset;
        });

        if ($request->wantsJson()) {
            $preset->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Preset created successfully.',
                'preset' => $preset,
            ]);
        }

        if ($request->input('redirect_to') === 'shop-owner-orders-create') {
            return redirect()->route('shop-owner.orders.create')
                ->with('success', 'Custom list saved successfully.');
        }

        return redirect()->route('requisitions.presets.index')
            ->with('success', 'Preset created successfully.');
    }

    /**
     * Show the form for editing the specified preset.
     */
    public function edit(Request $request, ShopPreset $preset): View
    {
        $user = $request->user();
        if (! $user->shop_id || $preset->shop_id !== $user->shop_id) {
            abort(403, 'Unauthorized access to preset.');
        }

        $preset->load('items.product');

        $products = Product::where('is_active', true)
            ->ordered()
            ->get();

        return view('requisitions.presets.edit', compact('preset', 'products'));
    }

    /**
     * Update the specified preset in storage.
     */
    public function update(Request $request, ShopPreset $preset): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (! $user->shop_id || $preset->shop_id !== $user->shop_id) {
            abort(403, 'Unauthorized access to preset.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($preset, $request): void {
            $preset->update([
                'name' => $request->input('name'),
            ]);

            // Clear existing items and rebuild
            $preset->items()->delete();

            foreach ($request->input('items') as $item) {
                ShopPresetItem::create([
                    'shop_preset_id' => $preset->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                ]);
            }
        });

        if ($request->wantsJson()) {
            $preset->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Preset updated successfully.',
                'preset' => $preset,
            ]);
        }

        return redirect()->route('requisitions.presets.index')
            ->with('success', 'Preset updated successfully.');
    }

    /**
     * Remove the specified preset from storage.
     */
    public function destroy(Request $request, ShopPreset $preset): RedirectResponse
    {
        $user = $request->user();
        if (! $user->shop_id || $preset->shop_id !== $user->shop_id) {
            abort(403, 'Unauthorized access to preset.');
        }

        $preset->delete();

        return redirect()->route('requisitions.presets.index')
            ->with('success', 'Preset deleted successfully.');
    }
}
