<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StartUserImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && $this->user()->can('impersonate', $target);
    }

    public function rules(): array
    {
        return [];
    }
}
