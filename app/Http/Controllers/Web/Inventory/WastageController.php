<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\DTOs\Inventory\WastageEntryData;
use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Inventory\StoreWastageEntryRequest;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\WastageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WastageController extends Controller
{
    public function __construct(
        private readonly WastageService $service,
        private readonly ProductRepository $products,
    ) {}

    public function index(Request $request): View
    {
        $entries = $this->service->paginate(20);
        $todayCost = $this->service->todayTotalCost();

        return view('inventory.wastage.index', compact('entries', 'todayCost'));
    }

    public function create(): View
    {
        $products = $this->products->findAllActive();
        $grades = ProductGrade::cases();
        $reasons = WastageReason::cases();

        return view('inventory.wastage.create', compact('products', 'grades', 'reasons'));
    }

    public function store(StoreWastageEntryRequest $request): RedirectResponse
    {
        $this->service->record(WastageEntryData::fromRequest($request), $request->user()->id);

        return redirect()->route('inventory.wastage.index')
            ->with('success', 'Wastage entry recorded successfully.');
    }
}
