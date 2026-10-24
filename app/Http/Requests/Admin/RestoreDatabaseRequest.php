<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RestoreDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user && ($user->hasRole('admin') || $user->can('admin.user.view') || $user->can('admin.settings.view'))) {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = (int) config('backup.restore.max_upload_size_kb', 512000);
        $phrase = (string) config('backup.restore.confirmation_phrase', 'RESTORE DATABASE');

        return [
            'confirmation' => ['required', 'string', 'in:'.$phrase],
            'backup_file' => ['nullable', 'string', 'max:255', 'required_without:upload_file'],
            'upload_file' => [
                'nullable',
                'file',
                'max:'.$maxKb,
                'required_without:backup_file',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $phrase = (string) config('backup.restore.confirmation_phrase', 'RESTORE DATABASE');

        return [
            'confirmation.in' => "You must type '{$phrase}' exactly to confirm database replacement.",
            'confirmation.required' => 'Confirmation phrase is required to perform database restoration.',
            'backup_file.required_without' => 'Please select an existing backup file or upload a backup archive.',
            'upload_file.required_without' => 'Please select an existing backup file or upload a backup archive.',
            'upload_file.max' => 'The uploaded backup file exceeds the allowed upload limit.',
        ];
    }
}
