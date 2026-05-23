<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Purchasing;

use App\DTOs\Purchasing\PurchaseInvoiceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Purchasing\StorePurchaseInvoiceRequest;
use App\Http\Resources\Purchasing\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\Purchasing\PurchaseInvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly PurchaseInvoiceService $service,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PurchaseInvoice::class);

        $invoices = $this->service->paginate();

        return ApiResponse::paginated(PurchaseInvoiceResource::collection($invoices));
    }

    public function store(StorePurchaseInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->service->create(PurchaseInvoiceData::fromRequest($request));

        return ApiResponse::success(
            new PurchaseInvoiceResource($invoice->load(['goodsReceived', 'supplier'])),
            'Purchase invoice created successfully',
            201
        );
    }

    public function show(PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        Gate::authorize('view', $purchaseInvoice);

        return ApiResponse::success(
            new PurchaseInvoiceResource($purchaseInvoice->load(['goodsReceived', 'supplier']))
        );
    }

    public function updateStatus(Request $request, PurchaseInvoice $purchaseInvoice): JsonResponse
    {
        Gate::authorize('update', $purchaseInvoice);

        $request->validate([
            'status' => ['required', 'string', 'in:pending,approved,paid'],
        ]);

        $updated = $this->service->updateStatus($purchaseInvoice, $request->string('status')->toString());

        return ApiResponse::success(
            new PurchaseInvoiceResource($updated->load(['goodsReceived', 'supplier'])),
            'Purchase invoice status updated successfully'
        );
    }
}
