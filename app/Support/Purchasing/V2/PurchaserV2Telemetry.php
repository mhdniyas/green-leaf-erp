<?php

declare(strict_types=1);

namespace App\Support\Purchasing\V2;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PurchaserV2Telemetry
{
    private float $startTime;

    private int $startQueryCount;

    private float $startQueryTimeMs;

    private int $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(false);

        if (! DB::logging()) {
            DB::enableQueryLog();
        }

        $queryLog = DB::getQueryLog();
        $this->startQueryCount = count($queryLog);
        $this->startQueryTimeMs = (float) array_sum(array_column($queryLog, 'time'));
    }

    public static function start(): self
    {
        return new self;
    }

    /**
     * @return array{
     *     time_ms: float,
     *     query_count: int,
     *     query_time_ms: float,
     *     memory_mb: float,
     *     peak_memory_mb: float
     * }
     */
    public function metrics(): array
    {
        $durationMs = round((microtime(true) - $this->startTime) * 1000, 2);
        $currentQueryLog = DB::getQueryLog();
        $queryCount = max(0, count($currentQueryLog) - $this->startQueryCount);

        $recentQueries = array_slice($currentQueryLog, $this->startQueryCount);
        $queryTimeMs = round((float) array_sum(array_column($recentQueries, 'time')), 2);

        $peakMemoryMb = round(memory_get_peak_usage(false) / (1024 * 1024), 2);
        $deltaMemoryMb = round((memory_get_usage(false) - $this->startMemory) / (1024 * 1024), 2);

        return [
            'time_ms' => $durationMs,
            'query_count' => $queryCount,
            'query_time_ms' => $queryTimeMs,
            'memory_mb' => max(0, $deltaMemoryMb),
            'peak_memory_mb' => $peakMemoryMb,
        ];
    }

    /**
     * Attach headers to a response if permitted (debug mode enabled or admin user).
     */
    public function attachHeaders(SymfonyResponse $response, ?User $user = null): SymfonyResponse
    {
        $allowed = config('app.debug', false) === true
            || ($user !== null && ($user->hasRole('admin') || $user->can('admin.user.view')));

        if (! $allowed) {
            return $response;
        }

        $metrics = $this->metrics();
        $response->headers->set('X-V2-Time-Ms', (string) $metrics['time_ms']);
        $response->headers->set('X-V2-Queries', (string) $metrics['query_count']);
        $response->headers->set('X-V2-Query-Time-Ms', (string) $metrics['query_time_ms']);
        $response->headers->set('X-V2-Memory-Mb', (string) $metrics['peak_memory_mb']);

        return $response;
    }
}
