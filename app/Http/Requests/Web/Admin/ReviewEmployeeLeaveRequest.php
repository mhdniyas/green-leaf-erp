<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewEmployeeLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('leaveRequest'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string'],
        ];
    }
}
