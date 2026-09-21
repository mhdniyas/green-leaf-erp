<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Support\Purchasing\V2\PurchaserV2Telemetry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class PurchaserV2BaseController extends Controller
{
    public function __construct(
        protected readonly PurchaserBusinessDayService $businessDayService,
    ) {}

    /**
     * Authorize user for Purchaser V2 access.
     */
    protected function ensurePurchaser(Request $request): User
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('purchaser') && ! $user->hasRole('admin') && ! $user->hasRole('purchase'))) {
            abort(403, 'Unauthorized access to Purchaser V2.');
        }

        return $user;
    }

    /**
     * Resolve the active operational date safely.
     */
    protected function resolveBusinessDate(Request $request): Carbon
    {
        $operationalDate = $this->businessDayService->operationalDate();
        $dateParam = $request->query('date');

        if ($dateParam && is_string($dateParam)) {
            try {
                $parsed = Carbon::parse($dateParam)->startOfDay();

                // Non-admin purchasers can only operate on the active operational date
                if (! $request->user()?->hasRole('admin') && ! $parsed->isSameDay($operationalDate)) {
                    return $operationalDate;
                }

                return $parsed;
            } catch (\Throwable) {
                return $operationalDate;
            }
        }

        return $operationalDate;
    }

    /**
     * Resolve purchase grade (A or B, defaults to A).
     */
    protected function resolveGrade(Request $request): string
    {
        $grade = strtoupper((string) $request->query('grade', 'A'));

        return in_array($grade, ['A', 'B'], true) ? $grade : 'A';
    }

    /**
     * Attach safe telemetry headers if authorized.
     */
    protected function attachTelemetry(SymfonyResponse $response, PurchaserV2Telemetry $telemetry, ?User $user = null): SymfonyResponse
    {
        return $telemetry->attachHeaders($response, $user);
    }
}
