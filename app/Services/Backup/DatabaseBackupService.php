<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Mail\DatabaseBackupMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DatabaseBackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('app/backups');
        if (! File::isDirectory($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Generate a database backup file and return its absolute path.
     *
     * @return array{path: string, filename: string, size: int}
     */
    public function createBackup(?string $connectionName = null): array
    {
        $connectionName ??= config('database.default');
        $driver = config("database.connections.{$connectionName}.driver", 'sqlite');
        $dbName = config("database.connections.{$connectionName}.database", 'database');

        $timestamp = now()->format('Y_m_d_His');
        $safeDbName = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $dbName);
        $filename = "glfresh_{$safeDbName}_{$timestamp}.sql.gz";
        $filePath = "{$this->backupDir}/{$filename}";

        if ($driver === 'sqlite') {
            $this->backupSqlite($connectionName, $filePath);
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $this->backupMysql($connectionName, $filePath);
        } else {
            // Generic fallback for any PDO connection
            $this->backupGenericPdo($connectionName, $filePath);
        }

        if (! file_exists($filePath) || filesize($filePath) === 0) {
            throw new RuntimeException("Backup file was not created or is empty: {$filePath}");
        }

        return [
            'path' => $filePath,
            'filename' => $filename,
            'size' => filesize($filePath),
        ];
    }

    /**
     * Create backup and email it as an attachment.
     *
     * @return array{path: string, filename: string, size: int, email: string}
     */
    public function sendBackupToEmail(string $recipientEmail, ?User $triggeredBy = null, ?string $connectionName = null): array
    {
        $backup = $this->createBackup($connectionName);

        $connectionName ??= config('database.default');
        $dbConfig = config("database.connections.{$connectionName}", []);

        $stats = $this->getDatabaseStats($connectionName);

        $metadata = [
            'database_name' => $dbConfig['database'] ?? $connectionName,
            'driver' => $dbConfig['driver'] ?? 'unknown',
            'host' => $dbConfig['host'] ?? 'localhost',
            'triggered_by' => $triggeredBy?->name ?? 'System/Admin',
            'triggered_by_email' => $triggeredBy?->email ?? null,
            'table_count' => count($stats['tables'] ?? []),
            'created_at' => now()->toDateTimeString(),
        ];

        Mail::to($recipientEmail)->send(new DatabaseBackupMail(
            filePath: $backup['path'],
            fileName: $backup['filename'],
            fileSizeBytes: $backup['size'],
            dbMetadata: $metadata,
        ));

        Log::info("Database backup {$backup['filename']} sent to {$recipientEmail}");

        return [
            'path' => $backup['path'],
            'filename' => $backup['filename'],
            'size' => $backup['size'],
            'email' => $recipientEmail,
        ];
    }

    /**
     * Backup SQLite database.
     */
    private function backupSqlite(string $connectionName, string $targetGzPath): void
    {
        $dbPath = (string) config("database.connections.{$connectionName}.database");

        if ($dbPath !== ':memory:' && file_exists($dbPath)) {
            $gz = gzopen($targetGzPath, 'wb9');
            if (! $gz) {
                throw new RuntimeException("Cannot open {$targetGzPath} for writing.");
            }

            $handle = fopen($dbPath, 'rb');
            if (! $handle) {
                gzclose($gz);
                throw new RuntimeException("Cannot open SQLite file {$dbPath} for reading.");
            }

            while (! feof($handle)) {
                $buffer = fread($handle, 1024 * 64);
                if ($buffer !== false) {
                    gzwrite($gz, $buffer);
                }
            }

            fclose($handle);
            gzclose($gz);

            return;
        }

        // Memory or dynamically structured SQLite database
        $gz = gzopen($targetGzPath, 'wb9');
        if (! $gz) {
            throw new RuntimeException("Cannot open {$targetGzPath} for writing.");
        }

        $connection = DB::connection($connectionName);

        $header = "-- SQLite Database Backup\n"
            .'-- Generated: '.now()->toDateTimeString()."\n"
            .'-- Database: '.$dbPath."\n\n"
            ."PRAGMA foreign_keys = OFF;\n\n";

        gzwrite($gz, $header);

        $tables = $connection->select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $tableObj) {
            $tableName = $tableObj->name;
            $createSql = $tableObj->sql;

            if ($createSql) {
                gzwrite($gz, "DROP TABLE IF EXISTS `{$tableName}`;\n{$createSql};\n\n");

                $columns = $connection->getSchemaBuilder()->getColumnListing($tableName);
                $orderCol = $columns[0] ?? 'rowid';

                $connection->table($tableName)->orderBy($orderCol)->chunk(300, function ($rows) use ($gz, $tableName, $connection) {
                    foreach ($rows as $row) {
                        $rowArray = (array) $row;
                        $cols = array_keys($rowArray);
                        $quotedCols = array_map(fn ($col) => "`{$col}`", $cols);
                        $vals = array_map(function ($val) use ($connection) {
                            if ($val === null) {
                                return 'NULL';
                            }
                            if (is_numeric($val)) {
                                return (string) $val;
                            }
                            if (is_bool($val)) {
                                return $val ? '1' : '0';
                            }

                            return $connection->getPdo()->quote((string) $val);
                        }, array_values($rowArray));

                        $sql = "INSERT INTO `{$tableName}` (".implode(', ', $quotedCols).') VALUES ('.implode(', ', $vals).");\n";
                        gzwrite($gz, $sql);
                    }
                });

                gzwrite($gz, "\n");
            }
        }

        gzwrite($gz, "PRAGMA foreign_keys = ON;\n");
        gzclose($gz);
    }

    /**
     * Backup MySQL database using mysqldump if available, or pure PHP export.
     */
    private function backupMysql(string $connectionName, string $targetGzPath): void
    {
        $config = config("database.connections.{$connectionName}");
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? 3306);
        $database = (string) ($config['database'] ?? '');
        $username = (string) ($config['username'] ?? 'root');
        $password = (string) ($config['password'] ?? '');

        // Try mysqldump command line first if available
        $mysqldumpPath = $this->findMysqldumpBinary();
        if ($mysqldumpPath !== null) {
            try {
                $tempSql = "{$this->backupDir}/dump_".uniqid().'.sql';
                $passwordArg = $password !== '' ? '-p'.escapeshellarg($password) : '';
                $command = sprintf(
                    '%s -h %s -P %s -u %s %s %s > %s',
                    escapeshellcmd($mysqldumpPath),
                    escapeshellarg($host),
                    escapeshellarg($port),
                    escapeshellarg($username),
                    $passwordArg,
                    escapeshellarg($database),
                    escapeshellarg($tempSql)
                );

                $processResult = Process::run($command);
                if ($processResult->successful() && file_exists($tempSql) && filesize($tempSql) > 0) {
                    $this->gzipFile($tempSql, $targetGzPath);
                    File::delete($tempSql);

                    return;
                }
                if (file_exists($tempSql)) {
                    File::delete($tempSql);
                }
            } catch (\Throwable $e) {
                Log::warning('mysqldump failed, falling back to PHP dumper: '.$e->getMessage());
            }
        }

        // Pure PHP MySQL dump fallback
        $this->backupMysqlPhp($connectionName, $targetGzPath);
    }

    /**
     * Pure PHP MySQL exporter to gzip.
     */
    private function backupMysqlPhp(string $connectionName, string $targetGzPath): void
    {
        $gz = gzopen($targetGzPath, 'wb9');
        if (! $gz) {
            throw new RuntimeException("Cannot open {$targetGzPath} for writing.");
        }

        $connection = DB::connection($connectionName);

        $header = "-- ========================================================\n"
            ."-- Green Leaf ERP Database Backup\n"
            .'-- Generated: '.now()->toDateTimeString()."\n"
            .'-- Host: '.($connection->getConfig('host') ?? 'localhost')."\n"
            .'-- Database: '.($connection->getDatabaseName() ?? '')."\n"
            ."-- ========================================================\n\n"
            ."SET NAMES utf8mb4;\n"
            ."SET FOREIGN_KEY_CHECKS = 0;\n"
            ."SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
            ."SET AUTOCOMMIT = 0;\n"
            ."START TRANSACTION;\n\n";

        gzwrite($gz, $header);

        $tables = $connection->select('SHOW FULL TABLES WHERE Table_Type = "BASE TABLE"');

        foreach ($tables as $tableObj) {
            $tableArray = (array) $tableObj;
            $tableName = (string) reset($tableArray);

            $createTableResult = (array) $connection->selectOne("SHOW CREATE TABLE `{$tableName}`");
            $createTableSql = $createTableResult['Create Table'] ?? null;

            if ($createTableSql) {
                $tableHeader = "\n-- --------------------------------------------------------\n"
                    ."-- Table structure for `{$tableName}`\n"
                    ."-- --------------------------------------------------------\n\n"
                    ."DROP TABLE IF EXISTS `{$tableName}`;\n"
                    ."{$createTableSql};\n\n"
                    ."-- Dumping data for table `{$tableName}`\n"
                    ."/*!40000 ALTER TABLE `{$tableName}` DISABLE KEYS */;\n";

                gzwrite($gz, $tableHeader);

                $columns = $connection->getSchemaBuilder()->getColumnListing($tableName);

                $connection->table($tableName)->orderBy($columns[0] ?? 'id')->chunk(300, function ($rows) use ($gz, $tableName, $connection) {
                    if ($rows->isEmpty()) {
                        return;
                    }

                    $insertPrefix = "INSERT INTO `{$tableName}` VALUES ";
                    $valuesSql = [];

                    foreach ($rows as $row) {
                        $rowArray = (array) $row;
                        $escapedValues = [];
                        foreach ($rowArray as $value) {
                            if ($value === null) {
                                $escapedValues[] = 'NULL';
                            } elseif (is_numeric($value)) {
                                $escapedValues[] = (string) $value;
                            } elseif (is_bool($value)) {
                                $escapedValues[] = $value ? '1' : '0';
                            } else {
                                $escapedValues[] = $connection->getPdo()->quote((string) $value);
                            }
                        }
                        $valuesSql[] = '('.implode(', ', $escapedValues).')';
                    }

                    $insertSql = $insertPrefix.implode(",\n", $valuesSql).";\n";
                    gzwrite($gz, $insertSql);
                });

                gzwrite($gz, "/*!40000 ALTER TABLE `{$tableName}` ENABLE KEYS */;\n");
            }
        }

        $footer = "\nCOMMIT;\n"
            ."SET FOREIGN_KEY_CHECKS = 1;\n"
            .'-- Dump completed on '.now()->toDateTimeString()."\n";

        gzwrite($gz, $footer);
        gzclose($gz);
    }

    /**
     * Generic PDO dump fallback.
     */
    private function backupGenericPdo(string $connectionName, string $targetGzPath): void
    {
        $gz = gzopen($targetGzPath, 'wb9');
        if (! $gz) {
            throw new RuntimeException("Cannot open {$targetGzPath} for writing.");
        }

        $connection = DB::connection($connectionName);
        $tables = $connection->getDoctrineSchemaManager()->listTableNames();

        gzwrite($gz, "-- Generic database export\n-- Generated: ".now()->toDateTimeString()."\n\n");

        foreach ($tables as $tableName) {
            $columns = $connection->getSchemaBuilder()->getColumnListing($tableName);
            $connection->table($tableName)->orderBy($columns[0] ?? 'id')->chunk(250, function ($rows) use ($gz, $tableName, $connection) {
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $cols = array_keys($rowArray);
                    $quotedCols = array_map(fn ($col) => "`{$col}`", $cols);
                    $vals = array_map(function ($val) use ($connection) {
                        return $val === null ? 'NULL' : $connection->getPdo()->quote((string) $val);
                    }, array_values($rowArray));

                    $sql = "INSERT INTO `{$tableName}` (".implode(', ', $quotedCols).') VALUES ('.implode(', ', $vals).");\n";
                    gzwrite($gz, $sql);
                }
            });
        }

        gzclose($gz);
    }

    /**
     * Compress plain file to gzip.
     */
    private function gzipFile(string $sourcePath, string $targetGzPath): void
    {
        $src = fopen($sourcePath, 'rb');
        $dest = gzopen($targetGzPath, 'wb9');

        if (! $src || ! $dest) {
            throw new RuntimeException("Unable to gzip file {$sourcePath}");
        }

        while (! feof($src)) {
            $chunk = fread($src, 1024 * 64);
            if ($chunk !== false) {
                gzwrite($dest, $chunk);
            }
        }

        fclose($src);
        gzclose($dest);
    }

    private function findMysqldumpBinary(): ?string
    {
        $candidates = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/homebrew/bin/mysqldump'];

        foreach ($candidates as $bin) {
            $result = Process::run("{$bin} --version");
            if ($result->successful()) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * Retrieve list of stored backups.
     *
     * @return array<int, array{filename: string, path: string, size: int, size_formatted: string, created_at: int, created_at_formatted: string}>
     */
    public function getBackupHistory(): array
    {
        if (! File::isDirectory($this->backupDir)) {
            return [];
        }

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (! str_ends_with($filename, '.gz') && ! str_ends_with($filename, '.sql') && ! str_ends_with($filename, '.sqlite')) {
                continue;
            }

            $size = $file->getSize();
            $mtime = $file->getMTime();

            $backups[] = [
                'filename' => $filename,
                'path' => $file->getRealPath(),
                'size' => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at' => $mtime,
                'created_at_formatted' => date('Y-m-d H:i:s', $mtime),
            ];
        }

        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * Delete a backup file.
     */
    public function deleteBackup(string $filename): bool
    {
        $safeFilename = basename($filename);
        $targetPath = "{$this->backupDir}/{$safeFilename}";

        if (File::exists($targetPath)) {
            return File::delete($targetPath);
        }

        return false;
    }

    /**
     * Get database tables and summary stats.
     *
     * @return array{driver: string, database: string, host: string, total_size: string, tables: array<int, array{name: string, rows: int, size: string}>}
     */
    public function getDatabaseStats(?string $connectionName = null): array
    {
        $connectionName ??= config('database.default');
        $config = config("database.connections.{$connectionName}", []);
        $driver = $config['driver'] ?? 'sqlite';
        $database = (string) ($config['database'] ?? 'database');
        $host = (string) ($config['host'] ?? 'localhost');

        $tables = [];
        $totalBytes = 0;

        try {
            $connection = DB::connection($connectionName);

            if ($driver === 'sqlite') {
                if (file_exists($database)) {
                    $totalBytes = filesize($database);
                }
                $sqliteTables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($sqliteTables as $tableObj) {
                    $tName = $tableObj->name;
                    $count = (int) $connection->table($tName)->count();
                    $tables[] = [
                        'name' => $tName,
                        'rows' => $count,
                        'size' => '—',
                    ];
                }
            } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                $tableStats = $connection->select('
                    SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ?
                    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                ', [$database]);

                foreach ($tableStats as $stat) {
                    $dataSize = (int) ($stat->DATA_LENGTH + $stat->INDEX_LENGTH);
                    $totalBytes += $dataSize;
                    $tables[] = [
                        'name' => $stat->TABLE_NAME,
                        'rows' => (int) $stat->TABLE_ROWS,
                        'size' => $this->formatBytes($dataSize),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to gather DB stats: '.$e->getMessage());
        }

        return [
            'driver' => $driver,
            'database' => $database,
            'host' => $host,
            'total_size' => $this->formatBytes($totalBytes),
            'tables' => $tables,
        ];
    }

    public function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $size = $bytes / pow(1024, $power);

        return round($size, $precision).' '.$units[$power];
    }
}
