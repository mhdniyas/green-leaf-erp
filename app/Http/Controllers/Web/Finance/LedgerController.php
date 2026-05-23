<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Finance\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('accounting.ledger.view');

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $accountId = $request->filled('account_id') ? $request->integer('account_id') : null;

        $accounts = Account::active()->orderBy('code')->get();

        $transactions = $this->ledgerService->getLedgerTransactions(
            startDate: $startDate ? (string) $startDate : null,
            endDate: $endDate ? (string) $endDate : null,
            accountId: $accountId
        );

        return view('finance.ledger.index', compact('accounts', 'transactions'));
    }
}
