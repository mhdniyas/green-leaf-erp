<?php

declare(strict_types=1);

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessDayCutoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user && ($user->hasRole('purchase') || $user->hasRole('admin') || $user->can('purchasing.order.approve'));
    }

    public function rules(): array
    {
        return [
            'cutoff_time' => ['required', 'date_format:H:i'],
            'redirect_date' => ['nullable', 'date'],
        ];
    }
}
