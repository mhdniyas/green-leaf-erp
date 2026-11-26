<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin\WarehouseCreditNotes;

use App\Models\User;
use App\Models\WarehouseCreditNote;
use App\Support\CashbookAccess;
use Illuminate\Foundation\Http\FormRequest;

class VoidWarehouseCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isMainAdmin() || $user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasRole('accounts') || ! $user->can(CashbookAccess::WarehouseCreditNotesVoid)) {
            return false;
        }

        $creditNote = $this->route('creditNote');

        return $creditNote instanceof WarehouseCreditNote
            ? $user->canAccessWarehouse((int) $creditNote->warehouse_id)
            : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'void_reason.required' => 'A cancellation reason is required.',
            'void_reason.min' => 'Cancellation reason must be at least 3 characters.',
        ];
    }
}
