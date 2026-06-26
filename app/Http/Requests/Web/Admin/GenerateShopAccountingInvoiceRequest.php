<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GenerateShopAccountingInvoiceRequest extends FormRequest
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
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
