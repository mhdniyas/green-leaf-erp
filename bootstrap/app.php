<?php

use App\Console\Commands\CancelOverduePurchaserWorkCommand;
use App\Console\Commands\CleanupLegacyStaffCommand;
use App\Console\Commands\RunAutoLoadAllCommand;
use App\Console\Commands\SeedDailyPriceMatrixNextDayCommand;
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
    ->withCommands([
        CleanupLegacyStaffCommand::class,
        CancelOverduePurchaserWorkCommand::class,
        RunAutoLoadAllCommand::class,
        SeedDailyPriceMatrixNextDayCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ApiVersionMiddleware::class,
        ]);

        $middleware->web(append: [
            SecureHeaders::class,
            UpdateLastSeen::class,
        ]);

        $middleware->append(SecureHeaders::class);
        $middleware->prepend(LogSlowPathPerformance::class);
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
                    if (! $request->routeIs('admin.accounting.index')) {
                        return redirect()->route('admin.accounting.index', ['date' => $navDate])->with('error', $errorMessage);
                    }
                }

                if (! $request->routeIs('dashboard')) {
                    return redirect()->route('dashboard')->with('error', $errorMessage);
                }

                return null;
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
                if (! $request->routeIs('finance.vendors.index')) {
                    return redirect()->route('finance.vendors.index')->with('error', $errorMessage);
                }
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
                if (! $request->routeIs('purchasing.dashboard')) {
                    return redirect()->route('purchasing.dashboard')->with('error', $errorMessage);
                }
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
                if (! $request->routeIs('inventory.dashboard')) {
                    return redirect()->route('inventory.dashboard', ['date' => $navDate])->with('error', $errorMessage);
                }
            }

            // Fallback: prevent circular redirect loops for dashboard and shop routes
            if ($request->routeIs('dashboard', 'shop.dashboard', 'shop-owner.*')) {
                return null;
            }

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

        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() >= 400 && ! $request->user() && ! auth()->check()) {
                $status = $response->getStatusCode();
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $exception instanceof HttpExceptionInterface ? $exception->getMessage() : 'Server Error',
                    ], $status);
                }

                $title = match ($status) {
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Page Not Found',
                    405 => 'Method Not Allowed',
                    419 => 'Page Expired',
                    429 => 'Too Many Requests',
                    default => 'Server Error',
                };

                return response()->make(
                    "<!DOCTYPE html><html><head><title>{$status} | {$title}</title><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><style>body{font-family:ui-sans-serif,system-ui,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}h1{font-size:1.5rem;font-weight:600;margin:0 0 0.5rem 0;}p{color:#94a3b8;font-size:0.95rem;margin:0;}</style></head><body><div style=\"text-align:center\"><h1>{$status} | {$title}</h1><p>Authorized testing staff should log in to view diagnostics.</p></div></body></html>",
                    $status,
                    ['Content-Type' => 'text/html']
                );
            }

            return $response;
        });
    })->create();
