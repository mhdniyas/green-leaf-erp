<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ! $request->user()->hasRole('admin')) {
            abort(403, 'Unauthorized access to activity logs.');
        }

        $query = Activity::query()->with([
            'causer' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    User::class => ['roles'],
                ]);
            },
            'subject',
        ]);

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

        if ($request->filled('ip_address')) {
            $query->where('properties->ip_address', 'like', '%'.$request->string('ip_address')->toString().'%');
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('properties->url', 'like', "%{$search}%")
                    ->orWhere('properties->ip_address', 'like', "%{$search}%");
            });
        }

        $filteredActivitiesCount = (clone $query)->count();
        $filteredUsersCount = (clone $query)
            ->whereNotNull('causer_id')
            ->distinct()
            ->count('causer_id');
        $filteredSubjectTypesCount = (clone $query)
            ->whereNotNull('subject_type')
            ->distinct()
            ->count('subject_type');

        // 2. Fetch Paginated Records
        $activities = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // 3. Fetch Filter Options
        $users = User::query()->select(['id', 'name', 'email'])->orderBy('name')->get();
        $events = Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event');
        $subjectTypes = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');
        $dailyActivityCounts = Activity::query()
            ->selectRaw('DATE(created_at) as activity_date, COUNT(*) as aggregate')
            ->whereBetween('created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('activity_date')
            ->pluck('aggregate', 'activity_date');

        return view('admin.activity_logs.index', compact(
            'activities',
            'users',
            'events',
            'subjectTypes',
            'startDate',
            'endDate',
            'filteredActivitiesCount',
            'filteredUsersCount',
            'filteredSubjectTypesCount',
            'dailyActivityCounts',
        ));
    }
}
