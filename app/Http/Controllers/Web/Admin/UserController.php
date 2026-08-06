<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\DTOs\Admin\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\StoreUserRequest;
use App\Http\Requests\Web\Admin\UpdateUserRequest;
use App\Models\Shop;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $scope = $request->string('scope')->toString() === 'pending' ? 'pending' : 'all';
        $availableRoles = collect($this->service->roleCounts());
        $selectedRole = $request->string('role')->toString();
        $role = $availableRoles->contains(fn (array $roleMeta): bool => $roleMeta['name'] === $selectedRole)
            ? $selectedRole
            : null;

        $users = $this->service->paginate(20, $request->input('search'), $scope, $role);
        $allUsersCount = User::query()->count();
        $pendingRegistrationsCount = User::query()->where('registration_status', 'pending')->count();

        return view('admin.users.index', compact(
            'users',
            'scope',
            'role',
            'availableRoles',
            'allUsersCount',
            'pendingRegistrationsCount',
        ));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        $roles = Role::withCount('permissions')->orderBy('name')->get();
        $shops = Shop::orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'shops'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->service->create(UserData::fromRequest($request));

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        $user->load('roles');
        $roles = Role::withCount('permissions')->orderBy('name')->get();
        $shops = Shop::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'shops'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->service->update($user, UserData::fromRequest($request));

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->service->delete($user);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} deleted successfully.");
    }

    public function approve(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->service->approve($user, request()->user());

        return redirect()->route('admin.users.index', ['scope' => 'pending'])
            ->with('success', "Registration for {$user->name} approved successfully.");
    }
}
