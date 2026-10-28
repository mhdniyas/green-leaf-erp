<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Enums\Inventory\ProductGrade;
use App\Enums\Inventory\StockMovementType;
use App\Enums\Inventory\WastageReason;
use App\Http\Controllers\Controller;
use App\Models\DailyInventoryCloseLine;
use App\Models\StockMovement;
use App\Models\WastageEntry;
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
            'lines.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
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
                $gradeEnum = ProductGrade::tryResolve($grade);
                $gradeVal = $gradeEnum?->value ?? $grade;
                $closingQty = round((float) $line['closing_qty'], 3);
                $wastageQty = round((float) ($line['wastage_qty'] ?? 0), 3);
                $carryoverQty = round((float) ($line['carryover_qty'] ?? 0), 3);
                $negativeNote = trim((string) ($line['negative_note'] ?? ''));
                $carryoverEnabled = (bool) ($line['carryover_enabled'] ?? false);
                $warehouseId = isset($line['warehouse_id']) ? (int) $line['warehouse_id'] : null;

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

                // Check existing line to prevent double deduction on repeat submission
                $existingLine = DailyInventoryCloseLine::query()
                    ->whereDate('business_date', $date)
                    ->where('product_id', $productId)
                    ->where('grade', $gradeVal)
                    ->lockForUpdate()
                    ->first();

                $alreadyWasted = $existingLine ? (float) $existingLine->wastage_qty : 0.0;
                $netWastageToDeduct = round($wastageQty - $alreadyWasted, 3);

                if ($netWastageToDeduct > 0.001) {
                    $this->stockLedgerService->consumeSortedStockForProduct(
                        productId: $productId,
                        quantity: $netWastageToDeduct,
                        userId: $userId,
                        movementType: StockMovementType::Wastage,
                        notes: "Daily inventory close wastage - Date: {$date}",
                        shopOrderItemId: null,
                        warehouseId: $warehouseId,
                        grade: $gradeEnum,
                        fallbackToUnsorted: false,
                        wastageDate: $date,
                        wastageReason: WastageReason::Unsold,
                    );
                } elseif ($netWastageToDeduct < -0.001) {
                    // Downward correction / reversal of previously recorded close wastage
                    $reversalQty = abs($netWastageToDeduct);
                    $this->reverseDailyCloseWastage($productId, $gradeVal, $date, $reversalQty);
                }

                if ($existingLine) {
                    $existingLine->update([
                        'closing_qty' => $closingQty,
                        'wastage_qty' => $wastageQty,
                        'carryover_qty' => $carryoverQty,
                        'negative_note' => $negativeNote !== '' ? $negativeNote : null,
                        'closed_by' => $userId,
                        'closed_at' => now(),
                    ]);
                } else {
                    DailyInventoryCloseLine::query()->create([
                        'business_date' => $date,
                        'product_id' => $productId,
                        'grade' => $gradeVal,
                        'closing_qty' => $closingQty,
                        'wastage_qty' => $wastageQty,
                        'carryover_qty' => $carryoverQty,
                        'negative_note' => $negativeNote !== '' ? $negativeNote : null,
                        'closed_by' => $userId,
                        'closed_at' => now(),
                    ]);
                }
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
        $normalized = ProductGrade::tryResolve($grade)?->value ?? $grade;

        return $productId.'|'.$normalized;
    }

    private function reverseDailyCloseWastage(int $productId, string $grade, string $date, float $reversalQty): void
    {
        $remainingToReverse = $reversalQty;

        // 1. Reverse WastageEntry records for this product, grade, and date
        $wastageEntries = WastageEntry::query()
            ->where('product_id', $productId)
            ->where('grade', $grade)
            ->whereDate('wastage_date', $date)
            ->orderByDesc('id')
            ->get();

        foreach ($wastageEntries as $entry) {
            if ($remainingToReverse <= 0.0001) {
                break;
            }
            $entryQty = (float) $entry->quantity;
            $reduction = min($remainingToReverse, $entryQty);
            $newQty = round($entryQty - $reduction, 3);
            if ($newQty <= 0.0001) {
                $entry->delete();
            } else {
                $entry->update(['quantity' => $newQty]);
            }
            $remainingToReverse -= $reduction;
        }

        // 2. Reverse StockMovement records of type Wastage for this product, grade, and date
        $remainingMovementToReverse = $reversalQty;
        $wastageMovements = StockMovement::query()
            ->where('product_id', $productId)
            ->where('grade', $grade)
            ->where('type', StockMovementType::Wastage->value)
            ->where('notes', 'like', "%Daily inventory close wastage - Date: {$date}%")
            ->orderByDesc('id')
            ->get();

        foreach ($wastageMovements as $movement) {
            if ($remainingMovementToReverse <= 0.0001) {
                break;
            }
            $movQty = (float) $movement->quantity;
            $reduction = min($remainingMovementToReverse, $movQty);
            $newQty = round($movQty - $reduction, 3);
            if ($newQty <= 0.0001) {
                $movement->delete();
            } else {
                $movement->update(['quantity' => $newQty]);
            }
            $remainingMovementToReverse -= $reduction;
        }
    }
}
