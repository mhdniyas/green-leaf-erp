<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Finance;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('accounting.ledger.view');

        $accounts = Account::orderBy('code')->get();

        return view('finance.accounts.index', compact('accounts'));
    }
}
