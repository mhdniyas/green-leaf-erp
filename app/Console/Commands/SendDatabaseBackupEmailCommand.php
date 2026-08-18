<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

class SendDatabaseBackupEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup-email {--email= : The email address to send the backup to} {--connection= : The database connection to backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a compressed database backup and send it via email';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        $recipientEmail = (string) $this->option('email');

        if (empty($recipientEmail)) {
            $recipientEmail = (string) config('admin.user_access.main_admin_email', config('mail.from.address'));
        }

        if (empty($recipientEmail)) {
            $this->error('Recipient email address is required. Use --email=user@example.com');

            return self::FAILURE;
        }

        $connection = $this->option('connection') ? (string) $this->option('connection') : null;

        $this->info("Creating database backup and sending to {$recipientEmail}...");

        try {
            $result = $backupService->sendBackupToEmail(
                recipientEmail: $recipientEmail,
                triggeredBy: null,
                connectionName: $connection
            );

            $this->info("✓ Database backup {$result['filename']} ({$result['size']} bytes) successfully emailed to {$recipientEmail}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to generate/email database backup: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
