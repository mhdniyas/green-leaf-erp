<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinalReportProductGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation by normalizing aliases.
     */
    protected function prepareForValidation(): void
    {
        $assignments = $this->input('assignments', []);
        if (is_array($assignments)) {
            foreach ($assignments as $index => $item) {
                if (isset($item['monthly_report_group'])) {
                    $group = (string) $item['monthly_report_group'];
                    $assignments[$index]['monthly_report_group'] = match ($group) {
                        'veg' => 'vegetables',
                        'other', '' => 'none',
                        default => $group,
                    };
                }
            }
            $this->merge(['assignments' => $assignments]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array'],
            'assignments.*.filter_id' => ['required', 'integer', 'exists:purchase_product_filters,id'],
            'assignments.*.monthly_report_group' => ['nullable', 'string', 'in:fruits,vegetables,stationery,none'],
        ];
    }
}
