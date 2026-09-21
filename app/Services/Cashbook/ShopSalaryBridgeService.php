<?php

declare(strict_types=1);

namespace App\Services\Cashbook;

use App\Enums\Cashbook\SalaryHrTransactionType;
use App\Models\Cashbook\ShopCashbookRelation;
use App\Models\Cashbook\ShopLedgerEntrySetting;
use App\Models\Cashbook\ShopSalaryBridgeSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShopSalaryBridgeService
{
    /**
     * Pure read method: Returns stored settings merged with unsaved in-memory defaults.
     * Does NOT mutate or insert rows into the database on read.
     *
     * @return Collection<string, ShopSalaryBridgeSetting>
     */
    public function getSettingsForShop(Shop $shop): Collection
    {
        $existing = ShopSalaryBridgeSetting::query()
            ->where('shop_id', $shop->id)
            ->with(['entrySetting.entryType', 'settlement'])
            ->get()
            ->keyBy(fn (ShopSalaryBridgeSetting $setting): string => $setting->typeEnum()->value);

        $defaultSettlement = ShopCashbookRelation::query()
            ->where('shop_id', $shop->id)
            ->where('enabled', true)
            ->where('is_company_payable', true)
            ->first();

        $result = collect();

        foreach (SalaryHrTransactionType::cases() as $type) {
            if ($existing->has($type->value)) {
                $result->put($type->value, $existing->get($type->value));

                continue;
            }

            // Construct unsaved model instance for display
            $defaults = self::defaultConfiguration();
            $fallbackSetting = new ShopSalaryBridgeSetting([
                'shop_id' => $shop->id,
                'transaction_type' => $type->value,
                'shop_ledger_entry_setting_id' => null,
                'default_payment_mode' => $defaults['default_payment_mode'],
                'allowed_payment_modes' => $defaults['allowed_payment_modes'],
                'company_payable_settlement_id' => $defaultSettlement?->id,
                'is_enabled' => true,
            ]);
            $fallbackSetting->exists = false;

            $result->put($type->value, $fallbackSetting);
        }

        return $result;
    }

    /**
     * Persists updated salary bridge configuration for a shop and records an audit log.
     *
     * @param  array<string, array{
     *     shop_ledger_entry_setting_id?: int|string|null,
     *     allowed_payment_modes?: array<int, string>,
     *     default_payment_mode?: string,
     *     company_payable_settlement_id?: int|string|null,
     *     is_enabled?: bool|int|string
     * }>  $typesData
     */
    public function updateSettingsForShop(Shop $shop, array $typesData, User $actor): Collection
    {
        return DB::transaction(function () use ($shop, $typesData, $actor): Collection {
            $beforeRecords = ShopSalaryBridgeSetting::query()
                ->where('shop_id', $shop->id)
                ->get()
                ->keyBy(fn (ShopSalaryBridgeSetting $s): string => $s->typeEnum()->value)
                ->toArray();

            $saved = collect();

            foreach (SalaryHrTransactionType::cases() as $type) {
                $input = $typesData[$type->value] ?? [];

                $entrySettingId = ! empty($input['shop_ledger_entry_setting_id'])
                    ? (int) $input['shop_ledger_entry_setting_id']
                    : null;

                // Validate that entry setting actually belongs to this shop
                if ($entrySettingId !== null) {
                    $validSetting = ShopLedgerEntrySetting::query()
                        ->where('id', $entrySettingId)
                        ->where('shop_id', $shop->id)
                        ->exists();

                    if (! $validSetting) {
                        $entrySettingId = null;
                    }
                }

                $rawAllowed = $input['allowed_payment_modes'] ?? [];
                $allowedModes = is_array($rawAllowed)
                    ? array_values(array_intersect($rawAllowed, ['sales_cash', 'petty', 'company_payable']))
                    : [];

                if (empty($allowedModes)) {
                    $allowedModes = ['sales_cash'];
                }

                $defaultMode = (string) ($input['default_payment_mode'] ?? 'sales_cash');
                if (! in_array($defaultMode, $allowedModes, true)) {
                    $defaultMode = $allowedModes[0];
                }

                $settlementId = null;
                if (in_array('company_payable', $allowedModes, true) && ! empty($input['company_payable_settlement_id'])) {
                    $candidateSettlementId = (int) $input['company_payable_settlement_id'];
                    $validSettlement = ShopCashbookRelation::query()
                        ->where('id', $candidateSettlementId)
                        ->where('shop_id', $shop->id)
                        ->exists();

                    if ($validSettlement) {
                        $settlementId = $candidateSettlementId;
                    }
                }

                $isEnabled = isset($input['is_enabled']) ? (bool) $input['is_enabled'] : true;

                $record = ShopSalaryBridgeSetting::query()->updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'transaction_type' => $type->value,
                    ],
                    [
                        'shop_ledger_entry_setting_id' => $entrySettingId,
                        'default_payment_mode' => $defaultMode,
                        'allowed_payment_modes' => $allowedModes,
                        'company_payable_settlement_id' => $settlementId,
                        'is_enabled' => $isEnabled,
                    ]
                );

                $record->loadMissing(['entrySetting.entryType', 'settlement']);
                $saved->put($type->value, $record);
            }

            $afterRecords = $saved->toArray();

            activity('cashbook_salary_settings')
                ->performedOn($shop)
                ->causedBy($actor)
                ->withProperties([
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'before' => $beforeRecords,
                    'after' => $afterRecords,
                ])
                ->log("Updated salary settings bridge configuration for {$shop->name}");

            return $saved;
        });
    }

    /**
     * The single canonical source for the default payment-mode configuration.
     * Used by: getSettingsForShop (fallback display), resetToDefault, seeders,
     * and new-shop initialisation. Do NOT duplicate this array elsewhere.
     *
     * @return array{allowed_payment_modes: array<int, string>, default_payment_mode: string}
     */
    public static function defaultConfiguration(): array
    {
        return [
            'allowed_payment_modes' => ['sales_cash', 'company_payable', 'petty'],
            'default_payment_mode' => 'sales_cash',
        ];
    }

    /**
     * Idempotently initialises missing salary bridge rows for a shop using the
     * canonical default configuration. Already-saved rows are untouched.
     *
     * Safe to call on every new-shop provisioning path.
     */
    public function initializeDefaultsForShop(Shop $shop): void
    {
        $defaults = self::defaultConfiguration();

        foreach (SalaryHrTransactionType::cases() as $type) {
            $exists = ShopSalaryBridgeSetting::query()
                ->where('shop_id', $shop->id)
                ->where('transaction_type', $type->value)
                ->exists();

            if ($exists) {
                continue;
            }

            ShopSalaryBridgeSetting::query()->create([
                'shop_id' => $shop->id,
                'transaction_type' => $type->value,
                'shop_ledger_entry_setting_id' => null,
                'allowed_payment_modes' => $defaults['allowed_payment_modes'],
                'default_payment_mode' => $defaults['default_payment_mode'],
                'company_payable_settlement_id' => null,
                'is_enabled' => true,
            ]);
        }
    }

    /**
     * Resets the payment-mode configuration for every salary type in a shop
     * back to the canonical default. Preserves:
     *   - shop_ledger_entry_setting_id  (mapped Cashbook category)
     *   - company_payable_settlement_id (configured settlement)
     *   - is_enabled
     *
     * Only resets:
     *   - allowed_payment_modes  → canonical default
     *   - default_payment_mode   → canonical default
     *
     * Records a single audit log entry. Does NOT move money or touch historical
     * payments.
     *
     * @return Collection<string, ShopSalaryBridgeSetting>
     */
    public function resetToDefault(Shop $shop, User $actor): Collection
    {
        $defaults = self::defaultConfiguration();

        return DB::transaction(function () use ($shop, $defaults, $actor): Collection {
            $beforeRecords = ShopSalaryBridgeSetting::query()
                ->where('shop_id', $shop->id)
                ->get()
                ->keyBy(fn (ShopSalaryBridgeSetting $s): string => $s->typeEnum()->value)
                ->toArray();

            $saved = collect();

            foreach (SalaryHrTransactionType::cases() as $type) {
                $record = ShopSalaryBridgeSetting::query()->updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'transaction_type' => $type->value,
                    ],
                    [
                        'allowed_payment_modes' => $defaults['allowed_payment_modes'],
                        'default_payment_mode' => $defaults['default_payment_mode'],
                        // Deliberately NOT touching: shop_ledger_entry_setting_id,
                        // company_payable_settlement_id, is_enabled
                    ]
                );

                $record->loadMissing(['entrySetting.entryType', 'settlement']);
                $saved->put($type->value, $record);
            }

            $afterRecords = $saved->toArray();

            activity('cashbook_salary_settings')
                ->performedOn($shop)
                ->causedBy($actor)
                ->withProperties([
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'action' => 'reset_to_default',
                    'before' => $beforeRecords,
                    'after' => $afterRecords,
                ])
                ->log("Reset salary settings to default for {$shop->name}");

            return $saved;
        });
    }

    /**
     * Generates structured view data for tree summary display.
     *
     * @param  Collection<string, ShopSalaryBridgeSetting>  $settings
     * @return array<int, array{
     *     type_key: string,
     *     type_label: string,
     *     category_name: string,
     *     is_configured: bool,
     *     default_mode_label: string,
     *     allowed_modes: array<string, bool>,
     *     settlement_name: ?string
     * }>
     */
    public function getTreeViewModel(Collection $settings): array
    {
        $items = [];

        foreach (SalaryHrTransactionType::cases() as $type) {
            /** @var ShopSalaryBridgeSetting|null $setting */
            $setting = $settings->get($type->value);

            $categoryName = $setting?->entrySetting
                ? ($setting->entrySetting->display_name ?: $setting->entrySetting->entryType?->name ?: 'Configured Category')
                : 'Not configured';

            $allowed = $setting?->allowed_payment_modes ?? ['sales_cash'];

            $defaultModeKey = $setting?->default_payment_mode ?? 'sales_cash';
            $defaultModeLabel = match ($defaultModeKey) {
                'petty' => 'Petty',
                'company_payable' => 'Company Payable',
                default => 'Sales Cash',
            };

            $settlementName = $setting?->settlement?->name ?? ($setting?->company_payable_settlement_id ? 'Settlement #'.$setting->company_payable_settlement_id : null);

            $items[] = [
                'type_key' => $type->value,
                'type_label' => $type->label(),
                'category_name' => $categoryName,
                'is_configured' => $setting?->shop_ledger_entry_setting_id !== null,
                'default_mode_label' => $defaultModeLabel,
                'allowed_modes' => [
                    'sales_cash' => in_array('sales_cash', $allowed, true),
                    'petty' => in_array('petty', $allowed, true),
                    'company_payable' => in_array('company_payable', $allowed, true),
                ],
                'settlement_name' => $settlementName,
            ];
        }

        return $items;
    }
}
