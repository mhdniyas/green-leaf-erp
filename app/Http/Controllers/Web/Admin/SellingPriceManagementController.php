<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellingPriceManagementController extends Controller
{
    public const float DEFAULT_PERCENTAGE = 25.0;

    public function index(Request $request): View|RedirectResponse
    {
        $this->authorizeAdmin($request);

        if ($request->input('tab') === 'categories') {
            return redirect()->route('admin.selling-price.categories.index');
        }

        if ($request->input('tab') === 'matrix') {
            return redirect()->route('admin.selling-price.matrix.index');
        }

        if (in_array($request->input('tab'), ['shop-pricing', 'shop', 'shops'], true)) {
            return redirect()->route('admin.selling-price.shop-pricing.index');
        }

        $rawPercentage = BusinessSetting::query()
            ->where('key', 'selling_price_default_percentage')
            ->value('value');

        $sellingPriceDefaultPercentage = ($rawPercentage !== null && is_numeric($rawPercentage) && (float) $rawPercentage >= 0)
            ? (float) $rawPercentage
            : self::DEFAULT_PERCENTAGE;

        return view('admin.selling-price.index', [
            'sellingPriceDefaultPercentage' => $sellingPriceDefaultPercentage,
            'activeTab' => 'general',
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $validated = $request->validate([
            'selling_price_default_percentage' => ['required', 'numeric', 'min:0', 'max:500'],
        ]);

        BusinessSetting::query()->updateOrCreate(
            ['key' => 'selling_price_default_percentage'],
            ['value' => (string) $validated['selling_price_default_percentage']],
        );

        return redirect()
            ->route('admin.selling-price.index')
            ->with('success', 'General selling price settings updated successfully.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
    }
}
