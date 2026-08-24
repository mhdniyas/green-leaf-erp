<?php

namespace Tests\Feature;

use App\Http\Middleware\LogSlowPathPerformance;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaserPerformanceTelemetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_purchaser_get_emits_redacted_performance_telemetry(): void
    {
        Role::findOrCreate('purchaser');
        $purchaser = User::factory()->create();
        $purchaser->assignRole('purchaser');
        Supplier::factory()->create();

        config(['logging.performance.enabled' => true]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'purchaser.performance'
                    && $context['route'] === 'purchaser.daily'
                    && $context['roles'] === ['purchaser']
                    && isset($context['request_id'], $context['duration_ms'], $context['query_count'], $context['query_time_ms'], $context['peak_memory_mb'], $context['payload_bytes'])
                    && ! array_key_exists('bindings', $context)
                    && ! array_key_exists('params', $context);
            });

        $this->actingAs($purchaser)->get(route('purchaser.daily'))->assertOk();
    }

    public function test_normalized_sql_redacts_literals_and_bindings(): void
    {
        $method = new \ReflectionMethod(LogSlowPathPerformance::class, 'normalizeSql');
        $normalizedSql = $method->invoke(new LogSlowPathPerformance, "select * from users where email = 'buyer@example.test' and id = 42");

        $this->assertSame('select * from users where email = ? and id = ?', $normalizedSql);
    }
}
