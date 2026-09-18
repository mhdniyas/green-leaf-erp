<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing;

use App\Actions\Purchasing\SubmitPurchaserBusinessDayAction;
use App\Http\Controllers\Controller;
use App\Models\Purchasing\PurchaserBusinessDaySubmission;
use App\Models\User;
use App\Services\Purchasing\PurchaserBusinessDayCalendarService;
use App\Services\Purchasing\PurchaserBusinessDayReconciliationService;
use App\Services\Purchasing\PurchaserBusinessDayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaserBusinessDaySubmissionController extends Controller
{
    public function __construct(
        private readonly PurchaserBusinessDayReconciliationService $reconciliationService,
        private readonly PurchaserBusinessDayService $businessDayService,
        private readonly PurchaserBusinessDayCalendarService $calendarService,
        private readonly SubmitPurchaserBusinessDayAction $submitAction,
    ) {}

    /**
     * Display business day verification & submission page with monthly calendar.
     */
    public function show(Request $request): View
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $purchaserId = $request->integer('purchaser_id', $currentUser->id);

        // Security check: non-admin purchasers can only view their own business day
        $isAdmin = $currentUser->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']);
        if (! $isAdmin && $purchaserId !== $currentUser->id) {
            $purchaserId = $currentUser->id;
        }

        $businessDate = $request->input('date');
        if (blank($businessDate) || ! is_string($businessDate)) {
            $businessDate = $this->businessDayService->operationalDate()->format('Y-m-d');
        } else {
            try {
                $businessDate = Carbon::parse($businessDate)->format('Y-m-d');
            } catch (\Throwable) {
                $businessDate = $this->businessDayService->operationalDate()->format('Y-m-d');
            }
        }

        $month = $request->input('month');
        if (blank($month) || ! is_string($month)) {
            $month = Carbon::parse((string) $businessDate)->format('Y-m');
        } else {
            try {
                $month = Carbon::parse($month.'-01')->format('Y-m');
            } catch (\Throwable) {
                $month = Carbon::parse((string) $businessDate)->format('Y-m');
            }
        }

        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;

        // Fetch monthly calendar data
        $calendar = $this->calendarService->getMonthlyCalendar(
            purchaserUserId: $purchaserId,
            yearMonth: (string) $month,
            warehouseId: $warehouseId,
            selectedDate: (string) $businessDate
        );

        // Fetch live Phase 2 reconciliation for selected date
        $reconciliation = $this->reconciliationService->calculateReconciliation(
            $purchaserId,
            (string) $businessDate,
            $warehouseId
        );

        // Fetch historical submission snapshot if exists
        $submission = PurchaserBusinessDaySubmission::query()
            ->with(['items.product', 'submittedBy'])
            ->where('purchaser_user_id', $purchaserId)
            ->whereDate('business_date', (string) $businessDate)
            ->first();

        return view('purchasing.purchaser.business_day_verification', [
            'calendar' => $calendar,
            'reconciliation' => $reconciliation,
            'submission' => $submission,
            'businessDate' => $businessDate,
            'month' => $month,
            'purchaserId' => $purchaserId,
            'warehouseId' => $warehouseId,
            'isSubmitted' => $submission !== null,
        ]);
    }

    /**
     * Submit business day verification and save immutable snapshot.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = $request->user();

        $validated = $request->validate([
            'business_date' => ['required', 'date_format:Y-m-d'],
            'purchaser_id' => ['nullable', 'integer', 'exists:users,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchaserUserId = (int) ($validated['purchaser_id'] ?? $currentUser->id);

        // Security check for store action
        $isAdmin = $currentUser->hasAnyRole(['admin', 'manager', 'accounts', 'accountant']);
        if (! $isAdmin && $purchaserUserId !== $currentUser->id) {
            $purchaserUserId = $currentUser->id;
        }

        try {
            $submission = $this->submitAction->execute(
                purchaserUserId: $purchaserUserId,
                businessDate: $validated['business_date'],
                warehouseId: $validated['warehouse_id'] ?? null,
                note: $validated['note'] ?? null,
                actorUserId: $currentUser->id
            );

            return redirect()
                ->route('purchaser.business-day.show', [
                    'date' => $validated['business_date'],
                    'purchaser_id' => $purchaserUserId,
                ])
                ->with('success', "Business day ({$validated['business_date']}) submitted successfully.");
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }
}
