<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CashbookAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashbookUserAccessController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureCanManageUserAccess($request);

        $search = trim((string) $request->input('search', ''));
        $usersQuery = User::query()->with(['roles', 'permissions']);

        if ($search !== '') {
            $usersQuery->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->orderBy('name')->paginate(15)->withQueryString();

        $selectedUserId = $request->input('user_id');
        /** @var User|null $selectedUser */
        $selectedUser = $selectedUserId ? User::with(['roles', 'permissions'])->find($selectedUserId) : null;

        if (! $selectedUser && $users->isNotEmpty()) {
            $selectedUser = $users->first();
        }

        $sections = CashbookAccess::sections();
        $accessMode = $selectedUser ? CashbookAccess::userAccessMode($selectedUser) : 'no_access';
        $userDirectPermissions = $selectedUser ? $selectedUser->permissions->pluck('name')->all() : [];
        $userAllPermissions = $selectedUser ? $selectedUser->getAllPermissions()->pluck('name')->all() : [];
        $userCashbookPermissions = array_values(array_unique(array_filter(
            array_merge($userDirectPermissions, $userAllPermissions),
            fn ($p) => str_starts_with((string) $p, 'cashbook.')
        )));

        return view('admin.cashbook.user-access.index', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'sections' => $sections,
            'accessMode' => $accessMode,
            'userCashbookPermissions' => $userCashbookPermissions,
            'search' => $search,
        ]);
    }

    public function update(Request $request, ?User $user = null): RedirectResponse
    {
        $this->ensureCanManageUserAccess($request);

        if (! $user || ! $user->exists) {
            $validatedUser = $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
            ]);
            /** @var User $user */
            $user = User::findOrFail($validatedUser['user_id']);
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:no_access,read_only,full_access,custom'],
            'read_only_sections' => ['nullable', 'array'],
            'read_only_sections.*' => ['string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $mode = $validated['mode'];
        $rawPermissions = $validated['permissions'] ?? [];
        $readOnlySections = $validated['read_only_sections'] ?? null;

        $newCashbookPermissions = [];

        if ($mode === 'no_access') {
            $newCashbookPermissions = [];
        } elseif ($mode === 'read_only') {
            if ($readOnlySections !== null) {
                $sections = CashbookAccess::sections();
                $selectedViewPerms = [];
                foreach ($readOnlySections as $sectionKey) {
                    if (isset($sections[$sectionKey]['view_permission']) && $sections[$sectionKey]['view_permission']) {
                        $selectedViewPerms[] = $sections[$sectionKey]['view_permission'];
                    }
                }
                $newCashbookPermissions = array_values(array_unique($selectedViewPerms));
            } else {
                $normalViews = CashbookAccess::normalViewPermissions();
                $newCashbookPermissions = array_values(array_intersect($rawPermissions, $normalViews));
            }

            if (! empty($newCashbookPermissions)) {
                $newCashbookPermissions[] = CashbookAccess::Access;
            }
        } elseif ($mode === 'full_access') {
            // Give all normal view + manage permissions
            $newCashbookPermissions = array_merge(
                [CashbookAccess::Access],
                CashbookAccess::normalViewPermissions(),
                CashbookAccess::normalManagePermissions()
            );
            // If user previously had user-access.manage and is an admin, or explicitly passed:
            if (in_array(CashbookAccess::UserAccessManage, $rawPermissions, true)) {
                $newCashbookPermissions[] = CashbookAccess::UserAccessManage;
            }
        } else { // custom
            $newCashbookPermissions = $rawPermissions;

            // Manage implies View
            foreach (CashbookAccess::sections() as $section) {
                if ($section['manage_permission'] && in_array($section['manage_permission'], $newCashbookPermissions, true)) {
                    if ($section['view_permission'] && ! in_array($section['view_permission'], $newCashbookPermissions, true)) {
                        $newCashbookPermissions[] = $section['view_permission'];
                    }
                }
            }

            if (! empty($newCashbookPermissions) && ! in_array(CashbookAccess::Access, $newCashbookPermissions, true)) {
                $newCashbookPermissions[] = CashbookAccess::Access;
            }
        }

        CashbookAccess::updateUserPermissions($user, $newCashbookPermissions);

        return redirect()
            ->route('admin.cashbook.user-access.index', [
                'user_id' => $user->id,
                'search' => $request->input('search'),
            ])
            ->with('success', "Cashbook access updated successfully for {$user->name}.");
    }

    private function ensureCanManageUserAccess(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! CashbookAccess::allows($user, CashbookAccess::UserAccessManage)) {
            abort(403, 'Unauthorized. Cashbook User Access is restricted.');
        }
    }
}
