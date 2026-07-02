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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

        User::observe(UserObserver::class);
    }
}
