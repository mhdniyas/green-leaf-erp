<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Display the activity logs listing.
     */
    public function index(Request $request): View
    {
        if (! $request->user()->can('admin.activity-log.view') &&
            ! $request->user()->hasRole('legacy-admin') &&
            ! $request->user()->hasRole('admin') &&
            ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Unauthorized access to activity logs.');
        }

        $query = Activity::query()->with(['causer', 'subject']);

        // 1. Apply Filters
        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        // Date filter
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $startDate = $startDateInput ? Carbon::parse($startDateInput) : Carbon::today()->subDays(7);
        $endDate = $endDateInput ? Carbon::parse($endDateInput) : Carbon::today();

        $query->whereBetween('created_at', [
            $startDate->copy()->startOfDay(),
            $endDate->copy()->endOfDay(),
        ]);

        // 2. Fetch Paginated Records
        $activities = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // 3. Fetch Filter Options
        $users = User::orderBy('name')->get();
        $events = Activity::whereNotNull('event')->distinct()->pluck('event');
        $subjectTypes = Activity::whereNotNull('subject_type')->distinct()->pluck('subject_type');

        return view('admin.activity_logs.index', compact(
            'activities',
            'users',
            'events',
            'subjectTypes',
            'startDate',
            'endDate'
        ));
    }
}
