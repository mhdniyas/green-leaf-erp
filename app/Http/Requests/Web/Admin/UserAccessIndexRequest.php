<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UserAccessIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewUserAccess', User::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
