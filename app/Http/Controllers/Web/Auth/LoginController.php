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
    public function demo(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.demo-login', [
            'staffAccounts' => [
                ['name' => 'Administrator', 'role' => 'Admin', 'email' => 'admin@greenleaf.com', 'password' => 'Admin11'],
                ['name' => 'Purchase Manager', 'role' => 'Purchase', 'email' => 'purchase@greenleaf.com', 'password' => 'Purchase12'],
                ['name' => 'Warehouse Manager', 'role' => 'Warehouse', 'email' => 'warehouse@greenleaf.com', 'password' => 'Warehouse13'],
                ['name' => 'Purchaser Niyas', 'role' => 'Purchaser', 'email' => 'purchaser@greenleaf.com', 'password' => 'Purchaser14'],
                ['name' => 'Purchaser Fallback', 'role' => 'Purchaser', 'email' => 'purchaser2@greenleaf.com', 'password' => 'Purchaser15'],
                ['name' => 'Warehouse Receiver', 'role' => 'Receiver', 'email' => 'receiver@greenleaf.com', 'password' => 'Receiver16'],
            ],
            'shopAccounts' => [
                ['name' => 'Casio Shop', 'code' => 'SHOP-001', 'email' => 'shop@greenleaf.com', 'password' => 'Casio17'],
                ['name' => 'Budegere Shop', 'code' => 'SHOP-002', 'email' => 'shop-budegere@greenleaf.com', 'password' => 'Budegere18'],
                ['name' => 'Grancity Shop', 'code' => 'SHOP-003', 'email' => 'shop-grancity@greenleaf.com', 'password' => 'Grancity19'],
                ['name' => 'Ashirwad Shop', 'code' => 'SHOP-004', 'email' => 'shop-ashirwad@greenleaf.com', 'password' => 'Ashirwad20'],
                ['name' => 'Metro Shop', 'code' => 'SHOP-005', 'email' => 'shop-metro@greenleaf.com', 'password' => 'Metro21'],
                ['name' => 'Reliance Shop', 'code' => 'SHOP-006', 'email' => 'shop-reliance@greenleaf.com', 'password' => 'Reliance22'],
                ['name' => 'Spar Shop', 'code' => 'SHOP-007', 'email' => 'shop-spar@greenleaf.com', 'password' => 'Spar23'],
                ['name' => 'More Shop', 'code' => 'SHOP-008', 'email' => 'shop-more@greenleaf.com', 'password' => 'More24'],
                ['name' => 'Lulu Shop', 'code' => 'SHOP-009', 'email' => 'shop-lulu@greenleaf.com', 'password' => 'Lulu25'],
                ['name' => 'Star Shop', 'code' => 'SHOP-010', 'email' => 'shop-star@greenleaf.com', 'password' => 'Star26'],
                ['name' => 'Foodworld Shop', 'code' => 'SHOP-011', 'email' => 'shop-foodworld@greenleaf.com', 'password' => 'Foodworld27'],
                ['name' => 'Nilgiris Shop', 'code' => 'SHOP-012', 'email' => 'shop-nilgiris@greenleaf.com', 'password' => 'Nilgiris28'],
                ['name' => 'Dmart Shop', 'code' => 'SHOP-013', 'email' => 'shop-dmart@greenleaf.com', 'password' => 'Dmart29'],
                ['name' => 'Easyday Shop', 'code' => 'SHOP-014', 'email' => 'shop-easyday@greenleaf.com', 'password' => 'Easyday30'],
            ],
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
}
