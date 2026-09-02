<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class TestDatabaseSafetyGuardTest extends TestCase
{
    public function test_guard_allows_sqlite_in_memory(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->expectNotToPerformAssertions();
        $this->assertTestDatabaseSafety();
    }

    public function test_guard_rejects_green_leaf_erp_as_default_database(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'database' => 'green_leaf_erp',
                'host' => '127.0.0.1',
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/forbidden database/i');

        $this->assertTestDatabaseSafety();
    }

    public function test_guard_rejects_green_leaf_erp_case_insensitive(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'database' => 'GREEN_LEAF_ERP',
                'host' => '127.0.0.1',
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/forbidden database/i');

        $this->assertTestDatabaseSafety();
    }

    public function test_guard_rejects_secondary_connection_pointing_to_forbidden_database(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'database.connections.tenant_mysql' => [
                'driver' => 'mysql',
                'database' => 'green_leaf_erp',
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Connection \'tenant_mysql\' is configured for forbidden database/i');

        $this->assertTestDatabaseSafety();
    }

    public function test_guard_rejects_non_in_memory_default_connection_by_default(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'database' => 'custom_test_db',
            ],
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Standard tests must use SQLite :memory:/i');

        $this->assertTestDatabaseSafety();
    }
}
