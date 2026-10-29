<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Printing;

use App\Http\Controllers\Controller;
use App\Models\Printing\PrintV1Preset;
use App\Models\Printing\PrintV1PresetBatch;
use App\Services\Printing\PrintV1MatrixBuilderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintV1OutputController extends Controller
{
    public function __construct(
        private readonly PrintV1MatrixBuilderService $matrixBuilder,
    ) {}

    public function sortSheet(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date');
        $preset = $this->resolvePreset($request);
        $overrideSettings = $preset?->settings;

        $result = $this->matrixBuilder->build('sort-sheet', $date, $overrideSettings, $request->user()?->id);

        $companyName = 'Green Leaf Distribution';
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedAt = now()->format('d M Y, h:i A');

        return view('print-v1.views.sort-print', [
            'result' => $result,
            'filteredShops' => $result->filteredShops,
            'matrix' => $result->matrix,
            'productMeta' => $result->productMeta,
            'date' => $result->date,
            'selectedWarehouse' => $result->selectedWarehouse,
            'selectedGrades' => $result->selectedGrades,
            'settings' => $result->settings,
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
            'preset' => $preset,
        ]);
    }

    public function selection(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date');
        $preset = $this->resolvePreset($request);
        $overrideSettings = $preset?->settings;

        $result = $this->matrixBuilder->build('selection', $date, $overrideSettings, $request->user()?->id);

        $companyName = 'Green Leaf Distribution';
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedAt = now()->format('d M Y, h:i A');

        return view('print-v1.views.selection', [
            'result' => $result,
            'filteredShops' => $result->filteredShops,
            'matrix' => $result->matrix,
            'productMeta' => $result->productMeta,
            'date' => $result->date,
            'selectedWarehouse' => $result->selectedWarehouse,
            'selectedGrades' => $result->selectedGrades,
            'settings' => $result->settings,
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
            'preset' => $preset,
        ]);
    }

    public function segregationGrid(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date');
        $preset = $this->resolvePreset($request);
        $overrideSettings = $preset?->settings;

        $result = $this->matrixBuilder->build('segregation-grid', $date, $overrideSettings, $request->user()?->id);

        $companyName = 'Green Leaf Distribution';
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedAt = now()->format('d M Y, h:i A');

        return view('print-v1.views.segregation-grid', [
            'result' => $result,
            'filteredShops' => $result->filteredShops,
            'matrix' => $result->matrix,
            'productMeta' => $result->productMeta,
            'date' => $result->date,
            'selectedWarehouse' => $result->selectedWarehouse,
            'selectedGrades' => $result->selectedGrades,
            'settings' => $result->settings,
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
            'preset' => $preset,
        ]);
    }

    public function shopWisePortrait(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date');
        $preset = $this->resolvePreset($request);
        $overrideSettings = $preset?->settings;

        $result = $this->matrixBuilder->build('shop-wise-portrait', $date, $overrideSettings, $request->user()?->id);

        $companyName = 'Green Leaf Distribution';
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedAt = now()->format('d M Y, h:i A');

        return view('print-v1.views.shop-wise-portrait', [
            'result' => $result,
            'filteredShops' => $result->filteredShops,
            'matrix' => $result->matrix,
            'productMeta' => $result->productMeta,
            'date' => $result->date,
            'selectedWarehouse' => $result->selectedWarehouse,
            'selectedGrades' => $result->selectedGrades,
            'settings' => $result->settings,
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
            'preset' => $preset,
        ]);
    }

    public function batchPrint(Request $request): View
    {
        $this->authorizeAccess($request);

        $date = $request->input('date');
        $presetIds = (array) $request->input('preset_ids', []);

        if (empty($presetIds) && $request->filled('batch_id')) {
            $batch = PrintV1PresetBatch::where('uuid', $request->input('batch_id'))
                ->orWhere('id', $request->input('batch_id'))
                ->first();
            if ($batch) {
                $presetIds = $batch->preset_ids ?? [];
            }
        }

        $presets = PrintV1Preset::whereIn('uuid', $presetIds)
            ->orWhereIn('id', $presetIds)
            ->orderBy('sort_order')
            ->get();

        $batchItems = [];
        foreach ($presets as $preset) {
            $result = $this->matrixBuilder->build(
                $preset->section,
                $date,
                $preset->settings,
                $request->user()?->id
            );

            $batchItems[] = [
                'preset' => $preset,
                'result' => $result,
            ];
        }

        $companyName = 'Green Leaf Distribution';
        $generatedBy = $request->user()?->name ?? 'System';
        $generatedAt = now()->format('d M Y, h:i A');

        return view('print-v1.views.batch-print', [
            'batchItems' => $batchItems,
            'date' => $date ?: $this->matrixBuilder->defaultOperationalDate(),
            'companyName' => $companyName,
            'generatedBy' => $generatedBy,
            'generatedAt' => $generatedAt,
        ]);
    }

    private function resolvePreset(Request $request): ?PrintV1Preset
    {
        $presetId = $request->input('preset');
        if (! $presetId) {
            return null;
        }

        return PrintV1Preset::where('uuid', $presetId)
            ->orWhere('id', $presetId)
            ->first();
    }

    private function authorizeAccess(Request $request): void
    {
        if (! $request->user()?->can('sort.sheet.view')) {
            abort(403, 'Unauthorized access to Printing V1 output.');
        }
    }
}
