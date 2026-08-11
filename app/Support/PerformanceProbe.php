<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;

class PerformanceProbe
{
    private float $startedAt;

    private float $lastCheckpointAt;

    /**
     * @var array<int, array{name: string, elapsed_ms: float, total_ms: float}>
     */
    private array $checkpoints = [];

    private function __construct(
        private readonly string $name,
        private readonly array $context = [],
    ) {
        $this->startedAt = microtime(true);
        $this->lastCheckpointAt = $this->startedAt;
    }

    public static function start(string $name, array $context = []): ?self
    {
        if (! (bool) config('logging.performance.enabled', false)) {
            return null;
        }

        return new self($name, $context);
    }

    public function checkpoint(string $name): void
    {
        $now = microtime(true);

        $this->checkpoints[] = [
            'name' => $name,
            'elapsed_ms' => round(($now - $this->lastCheckpointAt) * 1000, 2),
            'total_ms' => round(($now - $this->startedAt) * 1000, 2),
        ];

        $this->lastCheckpointAt = $now;
    }

    public function finish(array $context = []): void
    {
        Log::info('slow_path.checkpoints', [
            'name' => $this->name,
            'duration_ms' => round((microtime(true) - $this->startedAt) * 1000, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'context' => array_merge($this->context, $context),
            'checkpoints' => $this->checkpoints,
        ]);
    }
}
