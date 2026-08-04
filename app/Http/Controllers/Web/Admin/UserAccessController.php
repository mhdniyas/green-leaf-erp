<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StartUserImpersonationRequest;
use App\Http\Requests\Web\Admin\UserAccessIndexRequest;
use App\Models\User;
use App\Services\Admin\UserImpersonationService;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserAccessController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly UserImpersonationService $impersonation,
    ) {}

    public function index(UserAccessIndexRequest $request): View
    {
        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $search = $search !== '' ? $search : null;

        $users = $this->users->paginateForUserAccess(20, $search);
        $summary = $this->users->userAccessSummary($search);

        return view('admin.user-access.index', compact('users', 'summary', 'search'));
    }

    public function store(StartUserImpersonationRequest $request, User $user): RedirectResponse
    {
        $this->impersonation->start($request, $request->user(), $user);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Now viewing the application as '.$user->name.'.');
    }

    public function stop(Request $request): RedirectResponse
    {
        return $this->impersonation->stop($request);
    }
}
