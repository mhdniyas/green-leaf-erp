<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('auth.login');
    }

    /**
     * Show the hidden demo access page.
     */
    public function demo(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.demo-login', [
            'hasDemoAccess' => $request->session()->get('demo_access_granted', false),
            'staffAccounts' => $this->staffAccounts(),
        ]);
    }

    public function unlockDemoAccess(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_password' => ['required', 'string'],
        ]);

        if ($validated['page_password'] !== '2525') {
            return back()->withErrors(['page_password' => 'The access password is incorrect.']);
        }

        $request->session()->put('demo_access_granted', true);

        return redirect()->route('login.demo');
    }

    public function demoLogin(Request $request): RedirectResponse
    {
        abort_unless($request->session()->get('demo_access_granted', false), 403);

        $validated = $request->validate([
            'account' => ['required', 'string'],
        ]);

        $account = collect($this->staffAccounts())
            ->firstWhere('key', $validated['account']);

        abort_unless($account !== null, 404);

        if (! Auth::attempt([
            'email' => $account['email'],
            'password' => $account['password'],
        ])) {
            return redirect()->route('login.demo')
                ->withErrors(['page_password' => 'The selected demo account is unavailable.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
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
     * @return array<int, array{key: string, name: string, role: string, email: string, password: string}>
     */
    private function staffAccounts(): array
    {
        return [
            ['key' => 'admin', 'name' => 'Administrator', 'role' => 'Admin', 'email' => 'admin@greenleaf.com', 'password' => 'Admin11'],
            ['key' => 'purchase-manager', 'name' => 'Purchase Manager', 'role' => 'Purchase', 'email' => 'purchase@greenleaf.com', 'password' => 'Purchase12'],
            ['key' => 'warehouse-manager', 'name' => 'Warehouse Manager', 'role' => 'Warehouse', 'email' => 'warehouse@greenleaf.com', 'password' => 'Warehouse13'],
            ['key' => 'purchaser-niyas', 'name' => 'Purchaser Niyas', 'role' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'password' => 'Purchaser14'],
            ['key' => 'purchaser-fallback', 'name' => 'Purchaser Fallback', 'role' => 'Purchaser', 'email' => 'purchaser2@greenleaf.com', 'password' => 'Purchaser15'],
            ['key' => 'warehouse-receiver', 'name' => 'Warehouse Receiver', 'role' => 'Receiver', 'email' => 'receiver@greenleaf.com', 'password' => 'Receiver16'],
        ];
    }
}
