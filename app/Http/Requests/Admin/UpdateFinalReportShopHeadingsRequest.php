<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Services\Cashbook\MonthlyReport\ReportHeadingDictionary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinalReportShopHeadingsRequest extends FormRequest
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
        $shopId = $this->input('shop_id');
        $mappings = $this->input('mappings', []);

        if (is_array($mappings) && $shopId) {
            $settingsCache = null;
            foreach ($mappings as $index => $item) {
                if (empty($item['setting_id']) && ! empty($item['entry_type_id'])) {
                    if ($settingsCache === null) {
                        $settingsCache = ShopLedgerEntrySetting::query()
                            ->where('shop_id', (int) $shopId)
                            ->get()
                            ->keyBy('entry_type_id');
                    }
                    $setting = $settingsCache->get((int) $item['entry_type_id']);
                    if ($setting) {
                        $mappings[$index]['setting_id'] = $setting->id;
                    }
                }

                // Map legacy/alias heading buckets if supplied
                if (isset($item['monthly_report_bucket'])) {
                    $bucket = (string) $item['monthly_report_bucket'];
                    $mappings[$index]['monthly_report_bucket'] = match ($bucket) {
                        'bank', 'cash', 'card', 'credit' => null,
                        'expenses' => ReportHeadingDictionary::OTHER_EXPENSE,
                        'other_deductions' => ReportHeadingDictionary::OTHER_EXPENSE,
                        default => $bucket ?: null,
                    };
                }
            }
            $this->merge(['mappings' => $mappings]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'copy_from_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'mappings' => ['nullable', 'array'],
            'mappings.*.setting_id' => ['required_with:mappings', 'integer', 'exists:shop_ledger_entry_settings,id'],
            'mappings.*.monthly_report_bucket' => ['nullable', 'string', 'max:40'],
        ];
    }
}
