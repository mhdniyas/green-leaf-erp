<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VoidPurchaseCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof User) {
            return false;
        }

        return (method_exists($user, 'isMainAdmin') && $user->isMainAdmin())
            || $user->can('cashbook.reconciliation.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A valid void reason is required.',
            'reason.min' => 'The void reason must be at least 3 characters.',
        ];
    }
}
