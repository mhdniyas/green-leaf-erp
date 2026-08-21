<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiWarehouseLoadoutSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if (
            ! $request->user()?->hasRole('warehouse_receiver')
            && ! $request->user()?->hasRole('admin')
            && ! $request->user()?->hasRole('purchaser')
            && ! $request->user()?->can('warehouse.receive.confirm')
        ) {
            abort(403, 'Unauthorized access.');
        }

        $keys = [
            'auto_load_all_enabled',
            'auto_load_all_time',
            'auto_load_all_next_business_day',
            'auto_load_all_delay_seconds',
            'auto_load_all_allow_manual',
        ];

        $settings = BusinessSetting::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'auto_load_all_enabled' => filter_var($settings->get('auto_load_all_enabled') ?? false, FILTER_VALIDATE_BOOLEAN),
            'auto_load_all_time' => $settings->get('auto_load_all_time') ?: '00:15',
            'auto_load_all_next_business_day' => filter_var($settings->get('auto_load_all_next_business_day') ?? false, FILTER_VALIDATE_BOOLEAN),
            'auto_load_all_delay_seconds' => (int) ($settings->get('auto_load_all_delay_seconds') ?: 3),
            'auto_load_all_allow_manual' => filter_var($settings->get('auto_load_all_allow_manual') ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
