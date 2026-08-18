<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $dbMetadata
     */
    public function __construct(
        public readonly string $filePath,
        public readonly string $fileName,
        public readonly int $fileSizeBytes,
        public readonly array $dbMetadata = []
    ) {}

    public function envelope(): Envelope
    {
        $dbName = $this->dbMetadata['database_name'] ?? config('database.connections.'.config('database.default').'.database', 'Database');
        $date = now()->format('Y-m-d H:i:s');

        return new Envelope(
            subject: "[Green Leaf ERP] Database Backup ({$dbName}) - {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.database-backup',
            with: [
                'fileName' => $this->fileName,
                'fileSizeBytes' => $this->fileSizeBytes,
                'metadata' => $this->dbMetadata,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        $mimeType = str_ends_with($this->fileName, '.gz') ? 'application/gzip' : 'application/sql';

        return [
            Attachment::fromPath($this->filePath)
                ->as($this->fileName)
                ->withMime($mimeType),
        ];
    }
}
