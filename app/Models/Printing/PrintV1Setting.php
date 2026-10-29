<?php

declare(strict_types=1);

namespace App\Models\Printing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintV1Setting extends Model
{
    use HasFactory;

    protected $table = 'print_v1_settings';

    protected $fillable = [
        'section',
        'user_id',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * System default settings for each section.
     *
     * @return array<string, mixed>
     */
    public static function defaultSettings(string $section): array
    {
        return match ($section) {
            'sort-sheet' => [
                'warehouse_id' => null,
                'price_group_id' => null,
                'shop_id' => null,
                'category_ids' => [],
                'product_ids' => [],
                'grades' => ['A', 'B', 'C', 'U'],
                'skip_empty_shops' => true,
                'separate_category_pages' => false,
                'page_break_category_ids' => [],
                'show_category_titles' => true,
                'code_sort' => true,
                'show_code' => true,
            ],
            'selection' => [
                'warehouse_id' => null,
                'price_group_id' => null,
                'shop_id' => null,
                'category_ids' => [],
                'product_ids' => [],
                'grades' => ['A', 'B', 'C', 'U'],
                'skip_empty_shops' => true,
                'items_per_page' => 36,
            ],
            'segregation-grid' => [
                'warehouse_id' => null,
                'price_group_id' => null,
                'shop_id' => null,
                'category_ids' => [],
                'product_ids' => [],
                'grades' => ['A', 'B', 'C', 'U'],
                'skip_empty_shops' => true,
                'items_per_page' => 9,
            ],
            'shop-wise-portrait' => [
                'warehouse_id' => null,
                'price_group_id' => null,
                'shop_id' => null,
                'category_ids' => [],
                'product_ids' => [],
                'grades' => ['A', 'B', 'C', 'U'],
                'skip_empty_shops' => true,
                'orientation' => 'portrait',
            ],
            'shops' => [
                'included_inactive_shop_ids' => [],
                'excluded_active_shop_ids' => [],
                'default_skip_empty_shops' => true,
            ],
            default => [],
        };
    }

    /**
     * Resolve effective settings for a section:
     * User-specific override -> System default database record -> Code default fallback.
     *
     * @return array<string, mixed>
     */
    public static function getForSection(string $section, ?int $userId = null): array
    {
        $effective = self::defaultSettings($section);

        // 1. Merge system default record (user_id = null) if present
        $systemRecord = self::where('section', $section)
            ->whereNull('user_id')
            ->first();

        if ($systemRecord && is_array($systemRecord->settings)) {
            $effective = array_merge($effective, $systemRecord->settings);
        }

        // 2. Merge user-specific override if user ID provided and record exists
        if ($userId !== null) {
            $userRecord = self::where('section', $section)
                ->where('user_id', $userId)
                ->first();

            if ($userRecord && is_array($userRecord->settings)) {
                $effective = array_merge($effective, $userRecord->settings);
            }
        }

        return $effective;
    }

    /**
     * Persist settings for a section.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function setForSection(string $section, array $settings, ?int $userId = null): self
    {
        $existing = self::where('section', $section)
            ->where('user_id', $userId)
            ->first();

        $base = $existing && is_array($existing->settings)
            ? $existing->settings
            : self::defaultSettings($section);

        $merged = array_merge($base, $settings);

        return self::updateOrCreate(
            [
                'section' => $section,
                'user_id' => $userId,
            ],
            [
                'settings' => $merged,
            ]
        );
    }
}
