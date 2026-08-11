<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\UpdateInventorySettingsRequest;
use App\Services\Inventory\InventorySortingSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySettingsController extends Controller
{
    public function __construct(private readonly InventorySortingSettingsService $settings) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return view('inventory.settings.edit', [
            'sortAllAsGradeA' => $this->settings->sortAllAsGradeA(),
        ]);
    }

    public function update(UpdateInventorySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->settings->updateSortAllAsGradeA((bool) $validated['sort_all_as_grade_a']);

        return redirect()
            ->route('inventory.settings.edit')
            ->with('success', 'Inventory settings updated.');
    }
}
