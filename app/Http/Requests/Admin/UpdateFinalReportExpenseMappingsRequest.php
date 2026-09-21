<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinalReportExpenseMappingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        $mappings = $this->input('mappings', []);
        if (is_array($mappings)) {
            $cleanMappings = [];
            foreach ($mappings as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $sourceType = match ((string) ($item['source_type'] ?? '')) {
                    'procurement_expense', 'procurement_expense_category' => 'procurement_expense_category',
                    'other_expense', 'other_expense_category' => 'other_expense_category',
                    'company_accounting_category' => 'company_accounting_category',
                    default => (string) ($item['source_type'] ?? ''),
                };

                $sourceKey = (string) ($item['source_key'] ?? $item['source_id'] ?? '');
                $bucket = (string) ($item['report_bucket'] ?? $item['monthly_report_category'] ?? '');

                if ($sourceType !== '' && $sourceKey !== '') {
                    $cleanMappings[] = [
                        'source_type' => $sourceType,
                        'source_key' => $sourceKey,
                        'report_bucket' => $bucket ?: null,
                    ];
                }
            }
            $this->merge(['mappings' => $cleanMappings]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array'],
            'mappings.*.source_type' => ['required', 'string', 'max:80'],
            'mappings.*.source_key' => ['required', 'string', 'max:120'],
            'mappings.*.report_bucket' => ['nullable', 'string', 'max:40'],
        ];
    }
}
