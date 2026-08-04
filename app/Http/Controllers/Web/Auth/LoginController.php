<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use App\Models\User;
use App\Services\Admin\UserImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly UserImpersonationService $impersonation,
    ) {}

    /**
     * Show the login form.
     */
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
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

    /**
     * Log the user out of the application.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($this->impersonation->hasActiveSession($request)) {
            return $this->impersonation->stop($request, 'Returned to admin account.');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
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
