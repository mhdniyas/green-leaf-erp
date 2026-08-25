<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\GoodsReceived;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\CustomerPolicy;
use App\Policies\GoodsReceivedPolicy;
use App\Policies\PurchaseInvoicePolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Services\Purchasing\PurchaserBusinessDayService;
use App\Services\Purchasing\PurchaserCartBatchStateResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(PurchaserBusinessDayService::class);
        $this->app->scoped(PurchaserCartBatchStateResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceived::class, GoodsReceivedPolicy::class);
        Gate::policy(PurchaseInvoice::class, PurchaseInvoicePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // The new Cashbook is intentionally restricted to the configured main
        // administrator while it is being introduced. It must never inherit the
        // broader Shop Owner cashbook access rules.
        Gate::define('cashbook.admin.access', fn (User $user): bool => $user->isMainAdmin()
            || $user->hasRole('admin')
            || $user->hasRole('accounts')
            || $user->hasRole('accountant')
            || $user->hasRole('account')
            || $user->hasRole('manager'));

        RateLimiter::for('login', function ($request) {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('public-form', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        User::observe(UserObserver::class);

        Activity::creating(function (Activity $activity): void {
            if (app()->runningInConsole() && ! app()->environment('testing')) {
                return;
            }

            $properties = $activity->properties?->toArray() ?? [];

            $activity->properties = array_merge([
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'method' => request()->method(),
                'url' => request()->fullUrl(),
            ], $properties);
        });
    }
}
