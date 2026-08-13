<?php

declare(strict_types=1);

namespace Database\Seeders\Cashbook;

use App\Models\Cashbook\LedgerEntryType;
use App\Models\Cashbook\PresetEntrySetting;
use App\Models\Cashbook\ShopConfigPreset;
use Illuminate\Database\Seeder;

class ShopConfigPresetSeeder extends Seeder
{
    public function run(): void
    {
        $type = fn (string $code) => LedgerEntryType::where('code', $code)->firstOrFail();
        $base = [
            'version'        => 1,
            'effective_from' => '2026-01-01',
            'effective_to'   => null,
            'enabled'        => true,
        ];

        // ──────────────────────────────────────────────────────────────────────
        // Preset 1: Standard Aiswarya Veg Shop
        // ──────────────────────────────────────────────────────────────────────
        $standard = ShopConfigPreset::updateOrCreate(
            ['slug' => 'standard-veg-shop'],
            [
                'name'        => 'Standard Aiswarya Veg Shop',
                'description' => 'Default configuration for all owned Aiswarya Veg shops. Covers daily income, daily expense bills, vehicle expenses, petty float management and settlement.',
                'is_default'  => true,
                'enabled'     => true,
            ]
        );

        $this->seedPresetRules($standard, $base, $type, includeIncomeCpRent: false);

        // ──────────────────────────────────────────────────────────────────────
        // Preset 2: Grandcity Extended
        // ──────────────────────────────────────────────────────────────────────
        $grandcity = ShopConfigPreset::updateOrCreate(
            ['slug' => 'grandcity-extended'],
            [
                'name'        => 'Grandcity Extended (with Sub-Tenant Income)',
                'description' => 'Extended configuration for shops that earn sub-tenant income (income_cp, income_rent). Automatically generates counter expense entries.',
                'is_default'  => false,
                'enabled'     => true,
            ]
        );

        $this->seedPresetRules($grandcity, $base, $type, includeIncomeCpRent: true);

        // ──────────────────────────────────────────────────────────────────────
        // Preset 3: S/M Delivery Shops
        // ──────────────────────────────────────────────────────────────────────
        $smDelivery = ShopConfigPreset::updateOrCreate(
            ['slug' => 'sm-delivery-shops'],
            [
                'name'        => 'S/M Delivery Shops',
                'description' => 'Configuration for shops that record S/M delivery income and keep delivery deductions plus rent expense visible in cashbook.',
                'is_default'  => false,
                'enabled'     => true,
            ]
        );

        $this->seedSmDeliveryRules($smDelivery, $base, $type);
    }

    private function seedPresetRules(
        ShopConfigPreset $preset,
        array $base,
        \Closure $type,
        bool $includeIncomeCpRent
    ): void {
        $order = 1;
        foreach (['cash_sales', 'card', 'paytm', 'upi'] as $code) {
            PresetEntrySetting::updateOrCreate(
                ['preset_id' => $preset->id, 'entry_type_id' => $type($code)->id],
                $base + [
                    'default_funding_source'  => 'none',
                    'allowed_funding_sources' => ['none'],
                    'include_in_sales'        => true,
                    'include_in_income'       => true,
                    'include_in_pl'           => true,
                    'display_order'           => $order++,
                ]
            );
        }

        if ($includeIncomeCpRent) {
            PresetEntrySetting::updateOrCreate(
                ['preset_id' => $preset->id, 'entry_type_id' => $type('income_cp')->id],
                $base + [
                    'default_funding_source'    => 'none',
                    'allowed_funding_sources'   => ['none'],
                    'include_in_sales'          => true,
                    'include_in_income'         => true,
                    'include_in_pl'             => true,
                    'generates_secondary_entry' => true,
                    'secondary_entry_type_id'   => $type('cash_purchase')->id,
                    'secondary_amount_mode'     => 'same_amount',
                    'display_order'             => $order++,
                ]
            );

            PresetEntrySetting::updateOrCreate(
                ['preset_id' => $preset->id, 'entry_type_id' => $type('income_rent')->id],
                $base + [
                    'default_funding_source'    => 'none',
                    'allowed_funding_sources'   => ['none'],
                    'include_in_sales'          => true,
                    'include_in_income'         => true,
                    'include_in_pl'             => true,
                    'generates_secondary_entry' => true,
                    'secondary_entry_type_id'   => $type('rent_expense')->id,
                    'secondary_amount_mode'     => 'same_amount',
                    'display_order'             => $order++,
                ]
            );
        }

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('purchase_bill')->id],
            $base + [
                'default_funding_source'  => 'company',
                'allowed_funding_sources' => ['company'],
                'include_in_expense'      => true,
                'include_in_pl'           => true,
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('cash_purchase')->id],
            $base + [
                'default_funding_source'  => 'petty',
                'allowed_funding_sources' => ['petty', 'sales', 'company'],
                'include_in_expense'      => true,
                'include_in_pl'           => true,
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('vehicle')->id],
            $base + [
                'default_funding_source'   => 'company',
                'allowed_funding_sources'  => ['sales', 'petty', 'company'],
                'include_in_expense'       => true,
                'include_in_pl'            => true,
                'company_pending_behavior' => 'increase',
                'display_order'            => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('company_to_petty')->id],
            $base + [
                'default_funding_source'  => 'company',
                'allowed_funding_sources' => ['company'],
                'include_in_pl'           => false,
                'petty_behavior'          => 'increase',
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('sales_to_petty')->id],
            $base + [
                'default_funding_source'  => 'sales',
                'allowed_funding_sources' => ['sales'],
                'include_in_pl'           => false,
                'petty_behavior'          => 'increase',
                'settlement_behavior'     => 'decrease',
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('shop_paid_company')->id],
            $base + [
                'default_funding_source'  => 'sales',
                'allowed_funding_sources' => ['sales', 'company'],
                'include_in_pl'           => false,
                'settlement_behavior'     => 'decrease',
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('rent_expense')->id],
            $base + [
                'default_funding_source'  => 'sales',
                'allowed_funding_sources' => ['sales', 'petty', 'company'],
                'include_in_expense'      => true,
                'include_in_pl'           => true,
                'display_order'           => $order,
            ]
        );
    }

    private function seedSmDeliveryRules(
        ShopConfigPreset $preset,
        array $base,
        \Closure $type
    ): void {
        $order = 1;

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('income_s_m_delivery')->id],
            $base + [
                'default_funding_source'    => 'none',
                'allowed_funding_sources'   => ['none'],
                'include_in_sales'          => true,
                'include_in_income'         => true,
                'include_in_pl'             => true,
                'generates_secondary_entry' => true,
                'secondary_entry_type_id'   => $type('shop_deduct')->id,
                'secondary_amount_mode'     => 'same_amount',
                'display_order'             => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('shop_deduct')->id],
            $base + [
                'default_funding_source'  => 'none',
                'allowed_funding_sources' => ['none'],
                'include_in_expense'      => true,
                'include_in_pl'           => true,
                'display_order'           => $order++,
            ]
        );

        PresetEntrySetting::updateOrCreate(
            ['preset_id' => $preset->id, 'entry_type_id' => $type('rent_expense')->id],
            $base + [
                'default_funding_source'  => 'company',
                'allowed_funding_sources' => ['sales', 'petty', 'company'],
                'include_in_expense'      => true,
                'include_in_pl'           => true,
                'display_order'           => $order,
            ]
        );
    }
}
