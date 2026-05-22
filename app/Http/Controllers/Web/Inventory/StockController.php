<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Repositories\Inventory\StockMovementRepository;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
    ) {}

    public function index(): View
    {
        $stock = $this->stockMovements->currentStockByProductAndGrade();

        return view('inventory.stock.index', compact('stock'));
    }
}
