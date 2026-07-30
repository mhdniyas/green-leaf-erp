<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $demoUsers = $this->demoUsers();

        return view('auth.login', [
            'demoUsers' => $demoUsers,
            'demoUserSections' => $this->demoUserSections($demoUsers),
        ]);
    }

    public function demoIndex(): View|RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $demoUsers = $this->demoUsers();

        return view('auth.demo-login', [
            'demoUsers' => $demoUsers,
            'demoUserSections' => $this->demoUserSections($demoUsers),
        ]);
    }

    /**
     * Handle an authentication attempt.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if ($user !== null && ! $user->hasApprovedRegistration()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = $user->isPendingRegistration()
                    ? 'Your registration is pending admin approval.'
                    : 'Your account is not active. Contact an administrator.';

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => $message]);
            }

            if ($user !== null) {
                $this->ensureShopOwnerHasStoredShop($user);
            }

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'These credentials do not match our records.']);
    }

    public function demo(Request $request, User $user): RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        if (! $user->hasApprovedRegistration()) {
            $message = $user->isPendingRegistration()
                ? 'This demo user is pending admin approval.'
                : 'This demo user is not active.';

            return back()->withErrors(['email' => $message]);
        }

        Auth::login($user);
        $this->ensureShopOwnerHasStoredShop($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function demoPurchaser(): View|RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        if (Auth::check()) {
            return redirect()->route('purchaser.vendors');
        }

        $purchasers = User::role('purchaser')
            ->with('roles')
            ->orderBy('name')
            ->get();

        return view('auth.demo-purchaser', ['purchasers' => $purchasers]);
    }

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * @return Collection<int, User>
     */
    private function demoUsers(): Collection
    {
        if (app()->isProduction()) {
            return collect();
        }

        return User::query()
            ->with(['roles', 'shop'])
            ->orderByRaw("CASE WHEN email LIKE '%@greenleaf.com' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, array{title:string, users:Collection<int, User>}>
     */
    private function demoUserSections(Collection $demoUsers): Collection
    {
        $sections = [
            'admin' => ['title' => 'Admin', 'users' => collect()],
            'hr' => ['title' => 'HR', 'users' => collect()],
            'purchase' => ['title' => 'Purchase', 'users' => collect()],
            'warehouse' => ['title' => 'Warehouse', 'users' => collect()],
            'shop' => ['title' => 'Shop Owners', 'users' => collect()],
            'other' => ['title' => 'Other', 'users' => collect()],
        ];

        foreach ($demoUsers as $user) {
            $sections[$this->demoSectionKey($user)]['users']->push($user);
        }

        return collect($sections)
            ->filter(fn (array $section): bool => $section['users']->isNotEmpty())
            ->values();
    }

    private function demoSectionKey(User $user): string
    {
        $roles = $user->roles->pluck('name');

        if ($roles->contains('admin')) {
            return 'admin';
        }

        if ($roles->contains('hr_manager')) {
            return 'hr';
        }

        if ($roles->contains(fn (string $role): bool => str_contains($role, 'purchase') || $role === 'purchaser')) {
            return 'purchase';
        }

        if ($roles->contains('warehouse_receiver')) {
            return 'warehouse';
        }

        if ($roles->contains('shop')) {
            return 'shop';
        }

        return 'other';
    }

    private function ensureShopOwnerHasStoredShop(User $user): void
    {
        if (! $user->hasRole('shop') || $user->shop_id !== null) {
            return;
        }

        $shopId = $user->ownedShopAssignments()
            ->orderBy('shop_id')
            ->value('shop_id');

        if ($shopId === null) {
            return;
        }

        $user->forceFill(['shop_id' => $shopId])->save();
    }
}
