<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $stats = $this->backupService->getDatabaseStats();
        $backups = $this->backupService->getBackupHistory();
        $defaultRecipient = $request->user()?->email ?? '';

        return view('admin.backup.index', compact('stats', 'backups', 'defaultRecipient'));
    }

    public function sendEmail(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $result = $this->backupService->sendBackupToEmail(
                recipientEmail: $validated['email'],
                triggeredBy: $request->user(),
            );

            return back()->with('success', "Database backup '{$result['filename']}' successfully generated and emailed to {$validated['email']}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to generate and email database backup: '.$e->getMessage());
        }
    }

    public function download(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeAccess();

        $filename = $request->query('file');

        try {
            if ($filename) {
                $safeFilename = basename((string) $filename);
                $filePath = storage_path("app/backups/{$safeFilename}");

                if (! file_exists($filePath)) {
                    return back()->with('error', 'Requested backup file does not exist.');
                }

                return response()->download($filePath, $safeFilename, [
                    'Content-Type' => 'application/gzip',
                ]);
            }

            $backup = $this->backupService->createBackup();

            return response()->download($backup['path'], $backup['filename'], [
                'Content-Type' => 'application/gzip',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to create backup download: '.$e->getMessage());
        }
    }

    public function destroy(string $filename): RedirectResponse
    {
        $this->authorizeAccess();

        $deleted = $this->backupService->deleteBackup($filename);

        if ($deleted) {
            return back()->with('success', "Backup file '{$filename}' deleted successfully.");
        }

        return back()->with('error', "Could not find backup file '{$filename}' to delete.");
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('admin') || $user->can('admin.user.view') || $user->can('admin.settings.view'))) {
            return;
        }

        Gate::authorize('admin.user.view');
    }
}
