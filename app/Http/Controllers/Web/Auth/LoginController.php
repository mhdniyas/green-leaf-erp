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

        return view('auth.login', [
            'demoUsers' => $this->demoUsers(),
        ]);
    }

    public function demoIndex(): View|RedirectResponse
    {
        abort_if(app()->isProduction(), 404);

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.demo-login', [
            'demoUsers' => $this->demoUsers(),
        ]);
    }

    /**
     * Handle an authentication attempt.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if ($user !== null && $user->isPendingRegistration()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'Your registration is pending admin approval.']);
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

        if ($user->isPendingRegistration()) {
            return back()->withErrors(['email' => 'This demo user is pending admin approval.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
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
}
