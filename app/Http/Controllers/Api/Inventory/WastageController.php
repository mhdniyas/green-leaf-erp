<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Inventory;

use App\DTOs\Inventory\WastageEntryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Inventory\StoreWastageEntryRequest;
use App\Http\Resources\Inventory\WastageEntryResource;
use App\Services\Inventory\WastageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WastageController extends Controller
{
    public function __construct(
        private readonly WastageService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $entries = $this->service->paginate(
            perPage: 15,
            productId: $request->integer('product_id') ?: null,
            date: $request->string('date')->toString() ?: null,
        );

        return ApiResponse::paginated(WastageEntryResource::collection($entries));
    }

    public function store(StoreWastageEntryRequest $request): JsonResponse
    {
        $entry = $this->service->record(
            WastageEntryData::fromRequest($request),
            $request->user()->id
        );

        return ApiResponse::success(new WastageEntryResource($entry->load(['product', 'recordedBy'])), 'Wastage recorded successfully', 201);
    }
}
