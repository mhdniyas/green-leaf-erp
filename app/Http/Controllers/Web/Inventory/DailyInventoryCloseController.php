<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Enums\Inventory\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\DailyInventoryCloseLine;
use App\Repositories\Inventory\StockMovementRepository;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DailyInventoryCloseController extends Controller
{
    public function __construct(
        private readonly StockMovementRepository $stockMovements,
        private readonly StockLedgerService $stockLedgerService,
    ) {}

    public function index(Request $request): View
    {
        $date = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->toDateString()
            : today()->toDateString();
        $stockRows = $this->stockRows($date);
        $closedLines = DailyInventoryCloseLine::query()
            ->whereDate('business_date', $date)
            ->get()
            ->keyBy(fn (DailyInventoryCloseLine $line): string => $this->lineKey((int) $line->product_id, (string) $line->grade));

        return view('inventory.daily-close.index', compact('date', 'stockRows', 'closedLines'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'lines' => ['nullable', 'array'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.grade' => ['required', 'string', 'max:20'],
            'lines.*.closing_qty' => ['required', 'numeric'],
            'lines.*.wastage_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.carryover_qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.carryover_enabled' => ['nullable', 'boolean'],
            'lines.*.negative_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse((string) $validated['date'])->toDateString();
        $userId = (int) $request->user()->id;
        $errors = [];

        DB::transaction(function () use ($validated, $date, $userId, &$errors): void {
            foreach (($validated['lines'] ?? []) as $index => $line) {
                $productId = (int) $line['product_id'];
                $grade = (string) $line['grade'];
                $closingQty = round((float) $line['closing_qty'], 3);
                $wastageQty = round((float) ($line['wastage_qty'] ?? 0), 3);
                $carryoverQty = round((float) ($line['carryover_qty'] ?? 0), 3);
                $negativeNote = trim((string) ($line['negative_note'] ?? ''));
                $carryoverEnabled = (bool) ($line['carryover_enabled'] ?? false);

                if ($closingQty > 0.001) {
                    if (! $carryoverEnabled && $carryoverQty > 0.001) {
                        $errors["lines.{$index}.carryover_qty"] = 'This product is not enabled for carryover.';
                    }

                    if (abs(($wastageQty + $carryoverQty) - $closingQty) > 0.01) {
                        $errors["lines.{$index}.wastage_qty"] = 'Positive closing stock must be fully split between wastage and carryover.';
                    }
                }

                if ($closingQty < -0.001 && $negativeNote === '') {
                    $errors["lines.{$index}.negative_note"] = 'Negative stock needs an admin discrepancy note.';
                }

                if ($wastageQty > 0.001) {
                    $this->stockLedgerService->consumeSortedStockForProduct(
                        $productId,
                        $wastageQty,
                        $userId,
                        StockMovementType::Wastage,
                        "Daily inventory close wastage - Date: {$date}"
                    );
                }

                DailyInventoryCloseLine::query()->updateOrCreate(
                    [
                        'business_date' => $date,
                        'product_id' => $productId,
                        'grade' => $grade,
                    ],
                    [
                        'closing_qty' => $closingQty,
                        'wastage_qty' => $wastageQty,
                        'carryover_qty' => $carryoverQty,
                        'negative_note' => $negativeNote !== '' ? $negativeNote : null,
                        'closed_by' => $userId,
                        'closed_at' => now(),
                    ],
                );
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });

        return redirect()
            ->route('inventory.daily-close.index', ['date' => $date])
            ->with('success', 'Daily inventory close saved.');
    }

    private function stockRows(string $date)
    {
        return $this->stockMovements
            ->currentStockByProductAndGrade($date)
            ->sortBy([
                fn ($row): int => (float) $row->current_stock < 0 ? 0 : 1,
                fn ($row): string => (string) $row->product_name,
            ])
            ->values();
    }

    private function lineKey(int $productId, string $grade): string
    {
        return $productId.'|'.$grade;
    }
}
