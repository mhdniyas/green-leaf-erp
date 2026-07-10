<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Admin;

use App\Models\ShopAccountingEntry;
use App\Support\AccountingAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewShopAccountingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AccountingAccess::canReviewEntries($this->user());
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'recheck', 'review_lines'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'line_reviews' => ['nullable', 'array'],
            'line_reviews.*.decision' => ['nullable', 'string', Rule::in(['approve', 'recheck'])],
            'line_reviews.*.review_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $decision = (string) $this->input('decision');
                $lineReviews = $this->input('line_reviews', []);

                if ($decision === 'recheck' && blank($this->input('admin_note'))) {
                    $validator->errors()->add('admin_note', 'An admin note is required when sending recheck.');
                }

                if ($decision !== 'review_lines') {
                    return;
                }

                if (! is_array($lineReviews) || $lineReviews === []) {
                    $validator->errors()->add('line_reviews', 'Choose approve or recheck for at least one line item.');

                    return;
                }

                $entry = $this->route('entry');

                if (! $entry instanceof ShopAccountingEntry) {
                    $validator->errors()->add('line_reviews', 'The accounting entry is missing.');

                    return;
                }

                $validLineIds = $entry->lines()->pluck('id')->map(fn ($id): string => (string) $id)->all();
                $submittedLineIds = collect(array_keys($lineReviews))
                    ->map(fn ($lineId): string => (string) $lineId)
                    ->all();

                foreach ($submittedLineIds as $lineId) {
                    if (! in_array($lineId, $validLineIds, true)) {
                        $validator->errors()->add('line_reviews', 'Invalid line review selection.');
                    }
                }

                $selectedDecisions = collect($lineReviews)
                    ->pluck('decision')
                    ->filter(fn ($value): bool => in_array($value, ['approve', 'recheck'], true))
                    ->values();

                if ($selectedDecisions->isEmpty()) {
                    $validator->errors()->add('line_reviews', 'Choose approve or recheck for at least one line item.');
                }

                if ($selectedDecisions->contains('recheck') && blank($this->input('admin_note'))) {
                    $validator->errors()->add('admin_note', 'An admin note is required when any line item needs recheck.');
                }
            },
        ];
    }
}
