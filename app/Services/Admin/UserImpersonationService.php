<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class UserImpersonationService
{
    private const SESSION_ADMIN_ID = 'admin_impersonator_id';

    private const SESSION_ACTIVITY_ID = 'admin_impersonation_activity_id';

    private const SESSION_RETURN_URL = 'admin_impersonation_return_url';

    private const SESSION_STARTED_AT = 'admin_impersonation_started_at';

    private const SESSION_TARGET_ID = 'admin_impersonation_target_user_id';

    private const SESSION_TARGET_NAME = 'admin_impersonation_target_user_name';

    private const SESSION_TOKEN = 'admin_impersonation_token';

    public function start(Request $request, User $admin, User $target, ?string $returnUrl = null): void
    {
        abort_unless($admin->isMainAdmin(), 403, 'Only the main admin can use User Access.');
        abort_if($target->hasRole('admin'), 403, 'Admin accounts cannot be impersonated.');
        abort_unless($target->hasApprovedRegistration(), 403, 'Only approved users can be viewed.');

        $startedAt = now();
        $sessionToken = (string) Str::uuid();
        $activity = activity('user_access')
            ->causedBy($admin)
            ->performedOn($target)
            ->withProperties([
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'admin_email' => $admin->email,
                'selected_user_id' => $target->id,
                'selected_user_name' => $target->name,
                'selected_user_email' => $target->email,
                'selected_user_roles' => $target->roles()->pluck('name')->values()->all(),
                'selected_user_shop' => $target->shop?->name,
                'login_time' => $startedAt->toIso8601String(),
                'logout_time' => null,
                'impersonation_token' => $sessionToken,
            ])
            ->event('user_impersonation')
            ->log('User impersonation session started');

        Auth::login($target);
        $request->session()->regenerate();

        $request->session()->put([
            self::SESSION_ADMIN_ID => (int) $admin->id,
            self::SESSION_ACTIVITY_ID => (int) $activity->id,
            self::SESSION_RETURN_URL => $this->sanitizeReturnUrl($returnUrl, $request),
            self::SESSION_STARTED_AT => $startedAt->toIso8601String(),
            self::SESSION_TARGET_ID => (int) $target->id,
            self::SESSION_TARGET_NAME => $target->name,
            self::SESSION_TOKEN => $sessionToken,
        ]);
    }

    public function stop(Request $request, ?string $successMessage = null): RedirectResponse
    {
        $state = $this->state($request);
        abort_unless($state !== null, 403, 'No active user access session found.');

        $admin = User::query()->find($state['admin_id']);
        abort_unless($admin instanceof User && $admin->isMainAdmin(), 403, 'Unable to restore the original admin session.');

        $endedAt = now();
        $this->finalizeActivity($state, $endedAt);

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget($this->sessionKeys());

        return redirect()
            ->to($state['return_url'])
            ->with('success', $successMessage ?? 'Returned to admin account.');
    }

    public function hasActiveSession(Request $request): bool
    {
        return $this->state($request) !== null;
    }

    /**
     * @return array{
     *     activity_id:int,
     *     admin_id:int,
     *     return_url:string,
     *     started_at:?string,
     *     target_id:int,
     *     target_name:string,
     *     token:?string
     * }|null
     */
    private function state(Request $request): ?array
    {
        $adminId = (int) $request->session()->get(self::SESSION_ADMIN_ID, 0);
        $targetId = (int) $request->session()->get(self::SESSION_TARGET_ID, 0);
        $returnUrl = $request->session()->get(self::SESSION_RETURN_URL);

        if ($adminId <= 0 || $targetId <= 0 || ! is_string($returnUrl) || $returnUrl === '') {
            return null;
        }

        return [
            'activity_id' => (int) $request->session()->get(self::SESSION_ACTIVITY_ID, 0),
            'admin_id' => $adminId,
            'return_url' => $returnUrl,
            'started_at' => $request->session()->get(self::SESSION_STARTED_AT),
            'target_id' => $targetId,
            'target_name' => (string) $request->session()->get(self::SESSION_TARGET_NAME, ''),
            'token' => $request->session()->get(self::SESSION_TOKEN),
        ];
    }

    /**
     * @param  array{
     *     activity_id:int,
     *     admin_id:int,
     *     return_url:string,
     *     started_at:?string,
     *     target_id:int,
     *     target_name:string,
     *     token:?string
     * }  $state
     */
    private function finalizeActivity(array $state, Carbon $endedAt): void
    {
        if ($state['activity_id'] <= 0) {
            return;
        }

        $activity = Activity::query()->find($state['activity_id']);

        if (! $activity instanceof Activity) {
            return;
        }

        $properties = $activity->properties?->toArray() ?? [];
        $properties['logout_time'] = $endedAt->toIso8601String();
        $properties['selected_user_name'] = $properties['selected_user_name'] ?? $state['target_name'];
        $properties['selected_user_id'] = $properties['selected_user_id'] ?? $state['target_id'];
        $properties['impersonation_token'] = $properties['impersonation_token'] ?? $state['token'];

        $activity->forceFill([
            'description' => 'User impersonation session completed',
            'properties' => $properties,
        ])->save();
    }

    private function sanitizeReturnUrl(?string $returnUrl, Request $request): string
    {
        $candidate = is_string($returnUrl) && $returnUrl !== ''
            ? $returnUrl
            : url()->previous();

        if (! is_string($candidate) || $candidate === '') {
            return route('admin.user-access.index');
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        if ($host !== null && $host !== $request->getHost()) {
            return route('admin.user-access.index');
        }

        return $candidate;
    }

    /**
     * @return array<int, string>
     */
    private function sessionKeys(): array
    {
        return [
            self::SESSION_ADMIN_ID,
            self::SESSION_ACTIVITY_ID,
            self::SESSION_RETURN_URL,
            self::SESSION_STARTED_AT,
            self::SESSION_TARGET_ID,
            self::SESSION_TARGET_NAME,
            self::SESSION_TOKEN,
        ];
    }
}
