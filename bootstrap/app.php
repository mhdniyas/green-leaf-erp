<?php

use App\Http\Middleware\ApiVersionMiddleware;
use App\Http\Middleware\LogSlowPathPerformance;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\UpdateLastSeen;
use App\Models\PurchaseInvoice;
use App\Support\AccountingAccess;
use App\Support\StaffAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ApiVersionMiddleware::class,
        ]);

        $middleware->web(append: [
            SecureHeaders::class,
            UpdateLastSeen::class,
        ]);

        $middleware->append(SecureHeaders::class);
        $middleware->append(LogSlowPathPerformance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $redirectUnauthorizedDashboardRequest = function (Request $request) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return null;
            }

            $user = $request->user();
            $navDate = $request->input('date', today()->toDateString());
            $errorMessage = 'You do not have access to that page.';

            if ($request->routeIs('admin.staff.*')) {
                $staffLandingUrl = StaffAccess::landingUrl($user, $navDate);

                if ($staffLandingUrl !== null) {
                    return redirect()->to($staffLandingUrl)->with('error', $errorMessage);
                }
            }

            if ($request->routeIs('admin.overview')) {
                $staffLandingUrl = StaffAccess::landingUrl($user, $navDate);

                if ($staffLandingUrl !== null) {
                    return redirect()->to($staffLandingUrl)->with('error', $errorMessage);
                }
            }

            if ($request->routeIs('admin.accounting.*')) {
                if (AccountingAccess::canViewDashboard($user)) {
                    return redirect()->route('admin.accounting.index', ['date' => $navDate])->with('error', $errorMessage);
                }

                return redirect()->route('dashboard')->with('error', $errorMessage);
            }

            if (
                $request->routeIs(
                    'finance.index',
                    'finance.vendors.index',
                    'finance.sales.index',
                    'finance.vendor-daily',
                    'finance.sales-daily',
                    'finance.accounts.index',
                    'finance.ledger.index',
                    'finance.expenses.index',
                    'finance.expenses.create',
                    'finance.reports.pnl',
                    'finance.reports.balance-sheet',
                    'finance.reports.cash-flow'
                )
                && $user?->can('accounting.ledger.view')
            ) {
                return redirect()->route('finance.vendors.index')->with('error', $errorMessage);
            }

            if (
                (
                    $request->routeIs(
                        'purchasing.dashboard',
                        'purchasing.orders.index',
                        'purchasing.grns.index',
                        'purchasing.prices.index',
                        'purchasing.invoices.index',
                        'purchasing.shop-invoices.index'
                    )
                    || $request->routeIs('requisitions.board')
                    || $request->routeIs('requisitions.approved_board')
                )
                && (
                    $user?->hasRole('purchase')
                    || $user?->can('purchasing.supplier.view')
                    || $user?->can('purchasing.order.view')
                    || $user?->can('purchasing.grn.view')
                    || $user?->can('viewAny', PurchaseInvoice::class)
                )
            ) {
                return redirect()->route('purchasing.dashboard')->with('error', $errorMessage);
            }

            if (
                $request->routeIs(
                    'inventory.dashboard',
                    'inventory.deliveries.dashboard',
                    'inventory.stock.index',
                    'inventory.batches.index',
                    'inventory.wastage.index',
                    'inventory.sorting.checklist',
                    'inventory.sorting.shop-orders',
                    'inventory.sorting.shop-sorting',
                    'inventory.reports.fulfillment',
                    'inventory.products.index'
                )
                && $user?->hasAnyPermission(['inventory.product.view', 'inventory.stock.view', 'inventory.sorting.view', 'inventory.wastage.view'])
            ) {
                return redirect()->route('inventory.dashboard', ['date' => $navDate])->with('error', $errorMessage);
            }

            // Fallback: redirect to main dashboard for all other 403 errors
            return redirect()->route('dashboard')->with('error', $errorMessage);
        };

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($redirectUnauthorizedDashboardRequest) {
            return $redirectUnauthorizedDashboardRequest($request);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($redirectUnauthorizedDashboardRequest) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            return $redirectUnauthorizedDashboardRequest($request);
        });
    })->create();
