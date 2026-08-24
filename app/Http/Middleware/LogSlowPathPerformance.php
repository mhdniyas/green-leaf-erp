<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        if ($this->isPurchaserSafeGet($request)) {
            return $this->measurePurchaserSafeGet($request, $next);
        }

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

    private function isPurchaserSafeGet(Request $request): bool
    {
        return $request->isMethod('GET')
            && str_starts_with($request->path(), 'purchaser/');
    }

    private function measurePurchaserSafeGet(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $queries = [];
        $recordingQueries = true;

        DB::listen(function (QueryExecuted $query) use (&$queryCount, &$queryTimeMs, &$queries, &$recordingQueries): void {
            if (! $recordingQueries) {
                return;
            }

            $queryCount++;
            $queryTimeMs += $query->time;
            $normalizedSql = $this->normalizeSql($query->sql);

            $queries[$normalizedSql] ??= ['count' => 0, 'total_time_ms' => 0.0, 'max_time_ms' => 0.0];
            $queries[$normalizedSql]['count']++;
            $queries[$normalizedSql]['total_time_ms'] += $query->time;
            $queries[$normalizedSql]['max_time_ms'] = max($queries[$normalizedSql]['max_time_ms'], $query->time);
        });

        $response = $next($request);
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $recordingQueries = false;
        $roles = $request->user()?->getRoleNames()->values()->all() ?? [];
        $routeName = (string) $request->route()?->getName();

        Log::info('purchaser.performance', [
            'request_id' => $requestId,
            'route' => $routeName,
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'query_count' => $queryCount,
            'unique_query_count' => count($queries),
            'duplicate_query_count' => array_sum(array_map(fn (array $query): int => max(0, $query['count'] - 1), $queries)),
            'query_time_ms' => round($queryTimeMs, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'payload_bytes' => $this->payloadBytes($response),
            'roles' => $roles,
            'slow_queries' => collect($queries)
                ->sortByDesc('max_time_ms')
                ->take((int) config('logging.performance.max_slow_queries', 5))
                ->map(fn (array $query, string $sql): array => [
                    'sql' => $sql,
                    'count' => $query['count'],
                    'total_time_ms' => round($query['total_time_ms'], 2),
                    'max_time_ms' => round($query['max_time_ms'], 2),
                ])
                ->values()
                ->all(),
        ]);

        return $response;
    }

    private function normalizeSql(string $sql): string
    {
        $normalizedSql = preg_replace("/'(?:''|\\\\.|[^'])*'/", '?', $sql) ?? $sql;
        $normalizedSql = preg_replace('/\\b(?:0x[0-9a-f]+|\\d+(?:\\.\\d+)?)\\b/i', '?', $normalizedSql) ?? $normalizedSql;

        return preg_replace('/\s+/', ' ', trim($normalizedSql)) ?? $normalizedSql;
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
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return null;
        }

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
