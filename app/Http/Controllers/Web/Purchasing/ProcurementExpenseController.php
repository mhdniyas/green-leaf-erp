<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\ProcurementExpense;
use App\Models\PurchaserCart;
use App\Services\Finance\ProcurementExpensePostingService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcurementExpenseController extends Controller
{
    public function __construct(
        private readonly ProcurementExpensePostingService $postingService,
        private readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    public function index(Request $request): View
    {
        $this->ensurePurchaser($request);

        $date = Carbon::parse($request->input('date', $this->businessDayService->operationalDate()->toDateString()));
        $monthStart = $date->copy()->startOfMonth();
        $monthEnd = $date->copy()->endOfMonth();
        $editingExpense = $request->filled('edit')
            ? ProcurementExpense::query()->whereKey($request->integer('edit'))->where('user_id', $request->user()->id)->first()
            : null;

        $expenses = ProcurementExpense::query()
            ->with(['companyAccountingEntry.journalEntry'])
            ->where('user_id', $request->user()->id)
            ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $selectedDateExpenses = $expenses
            ->filter(fn (ProcurementExpense $expense): bool => $expense->expense_date?->isSameDay($date) === true)
            ->values();

        return view('purchasing.purchaser.procurement_expenses', [
            'date' => $date,
            'previousDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'categories' => ProcurementExpense::categories(),
            'expenses' => $expenses,
            'editingExpense' => $editingExpense,
            'selectedDateTotal' => round((float) $selectedDateExpenses->sum('amount'), 2),
            'monthlyTotal' => round((float) $expenses->sum('amount'), 2),
            'selectedDateCount' => $selectedDateExpenses->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensurePurchaser($request);

        $validated = $this->validatedPayload($request);

        $expense = DB::transaction(function () use ($request, $validated): ProcurementExpense {
            $expense = ProcurementExpense::query()->create([
                'user_id' => $request->user()->id,
                'expense_date' => Carbon::parse((string) $validated['expense_date'])->toDateString(),
                'category' => (string) $validated['category'],
                'amount' => round((float) $validated['amount'], 2),
                'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            ]);

            $this->postingService->syncDate($expense->expense_date, (int) $request->user()->id);

            return $expense->fresh('companyAccountingEntry') ?? $expense;
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $expense->expense_date->toDateString()])
            ->with('success', 'Procurement expense saved and posted to daily company expense.');
    }

    public function update(Request $request, ProcurementExpense $expense): RedirectResponse
    {
        $this->ensurePurchaser($request);
        $this->ensureExpenseOwner($request, $expense);

        $oldDate = $expense->expense_date?->copy();
        $validated = $this->validatedPayload($request);
        $newDate = Carbon::parse((string) $validated['expense_date']);

        DB::transaction(function () use ($expense, $oldDate, $newDate, $request, $validated): void {
            $expense->update([
                'expense_date' => $newDate->toDateString(),
                'category' => (string) $validated['category'],
                'amount' => round((float) $validated['amount'], 2),
                'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            ]);

            if ($oldDate instanceof Carbon && ! $oldDate->isSameDay($newDate)) {
                $this->postingService->syncDate($oldDate, (int) $request->user()->id);
            }

            $this->postingService->syncDate($newDate, (int) $request->user()->id);
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $newDate->toDateString()])
            ->with('success', 'Procurement expense updated and daily company expense resynced.');
    }

    public function destroy(Request $request, ProcurementExpense $expense): RedirectResponse
    {
        $this->ensurePurchaser($request);
        $this->ensureExpenseOwner($request, $expense);

        $date = $expense->expense_date?->copy() ?? today();
        DB::transaction(function () use ($expense, $date, $request): void {
            $expense->delete();
            $this->postingService->syncDate($date, (int) $request->user()->id);
        });

        return redirect()
            ->route('purchaser.procurement-expenses.index', ['date' => $date->toDateString()])
            ->with('success', 'Procurement expense deleted and daily company expense resynced.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', Rule::in(array_keys(ProcurementExpense::categories()))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function ensureExpenseOwner(Request $request, ProcurementExpense $expense): void
    {
        abort_unless((int) $expense->user_id === (int) $request->user()->id, 404);
    }

    private function ensurePurchaser(Request $request): void
    {
        if (
            ! $request->user()->hasRole('purchaser')
            && ! $request->user()->hasRole('admin')
            && ! $request->user()->hasRole('purchase')
        ) {
            abort(403, 'Unauthorized access.');
        }

        PurchaserCart::cancelOverdueCartsAndOrders($this->businessDayService->operationalDate());
    }
}
