<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewShopAccountingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && (
                $this->user()->hasRole('admin')
                || $this->user()->can('admin.user.view')
                || $this->user()->can('admin.daily-progress.view')
                || $this->user()->can('admin.activity-log.view')
            );
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'recheck'])],
            'admin_note' => ['nullable', 'string', 'max:2000', 'required_if:decision,recheck'],
        ];
    }
}
