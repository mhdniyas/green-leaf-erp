<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $view = $user->hasRole('shop') ? 'profile.shop-owner' : 'profile.index';

        return view($view, [
            'user' => $user,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (($validated['password'] ?? null) === null || $validated['password'] === '') {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('profile.show')
            ->with('success', 'Profile updated successfully.');
    }
}
