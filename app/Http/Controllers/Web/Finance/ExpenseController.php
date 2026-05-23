<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\DTOs\Finance\ExpenseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Finance\StoreExpenseRequest;
use App\Http\Requests\Web\Finance\UpdateExpenseRequest;
use App\Models\Account;
use App\Models\Expense;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    public function index(Request $request): View
    {
        if (! auth()->user()->can('accounting.report.view') && ! auth()->user()->can('accounting.entry.create')) {
            abort(403);
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;

        $accounts = Account::active()->expense()->orderBy('code')->get();
        $expenses = $this->service->paginate(
            perPage: 20,
            startDate: $startDate ? (string) $startDate : null,
            endDate: $endDate ? (string) $endDate : null,
            accountId: $accountId
        );

        return view('finance.expenses.index', compact('accounts', 'expenses'));
    }

    public function create(): View
    {
        Gate::authorize('accounting.entry.create');

        $accounts = Account::active()->expense()->orderBy('code')->get();

        return view('finance.expenses.create', compact('accounts'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->service->create(
            ExpenseData::fromRequest($request),
            (int) $request->user()->id
        );

        return redirect()->route('finance.expenses.index')
            ->with('success', 'Expense recorded and posted to general ledger successfully.');
    }

    public function edit(Expense $expense): View
    {
        Gate::authorize('accounting.entry.create');

        $accounts = Account::active()->expense()->orderBy('code')->get();

        return view('finance.expenses.edit', compact('expense', 'accounts'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->service->update($expense, ExpenseData::fromRequest($request));

        return redirect()->route('finance.expenses.index')
            ->with('success', 'Expense updated and ledger entries updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('accounting.entry.create');

        $this->service->delete($expense);

        return redirect()->route('finance.expenses.index')
            ->with('success', 'Expense deleted and ledger entries reversed successfully.');
    }
}
