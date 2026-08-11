<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogSlowPathPerformance
{
    /**
     * @var array<int, string>
     */
    private array $routeNames = [
        'api.v1.warehouse.loadout.index',
        'api.v1.warehouse.loadout.show',
        'api.v1.warehouse.loadout.save',
        'api.v1.warehouse.loadout.addons',
        'api.v1.warehouse.loadout.addon.store',
        'purchaser.vendors',
        'purchaser.bill',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('logging.performance.enabled', false)) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        if (! in_array($routeName, $this->routeNames, true)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $slowQueries = [];

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs, &$slowQueries): void {
            $queryCount++;
            $queryTimeMs += $query->time;

            if ($query->time >= (float) config('logging.performance.slow_query_ms', 100)) {
                $slowQueries[] = [
                    'time_ms' => round($query->time, 2),
                    'sql' => $query->sql,
                ];
            }
        });

        $response = $next($request);
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $payloadBytes = $this->payloadBytes($response);

        if ($this->shouldLog($durationMs, $queryCount, $queryTimeMs, $payloadBytes)) {
            Log::info('slow_path.performance', [
                'route' => $routeName,
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'query_count' => $queryCount,
                'query_time_ms' => round($queryTimeMs, 2),
                'payload_kb' => $payloadBytes === null ? null : round($payloadBytes / 1024, 2),
                'user_id' => $request->user()?->id,
                'params' => $request->query(),
                'slow_queries' => array_slice($slowQueries, 0, (int) config('logging.performance.max_slow_queries', 5)),
            ]);
        }

        return $response;
    }

    private function shouldLog(float $durationMs, int $queryCount, float $queryTimeMs, ?int $payloadBytes): bool
    {
        return $durationMs >= (float) config('logging.performance.min_duration_ms', 250)
            || $queryCount >= (int) config('logging.performance.min_query_count', 20)
            || $queryTimeMs >= (float) config('logging.performance.min_query_time_ms', 150)
            || ($payloadBytes !== null && $payloadBytes >= (int) config('logging.performance.min_payload_bytes', 30720));
    }

    private function payloadBytes(Response $response): ?int
    {
        if ($response->headers->has('Content-Length')) {
            return (int) $response->headers->get('Content-Length');
        }

        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();

            return $content === false ? null : strlen($content);
        }

        return null;
    }
}
