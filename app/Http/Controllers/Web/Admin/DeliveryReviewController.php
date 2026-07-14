<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeliveryReviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $reviews = ShopInvoice::query()
            ->with(['shop', 'order.shopCheckedBy'])
            ->whereHas('order', fn ($query) => $query
                ->where('delivery_status', 'pending_approval')
                ->where('delivery_review_status', 'pending'))
            ->latest('business_date')
            ->latest('id')
            ->paginate(20);

        return view('admin.delivery-reviews.index', [
            'reviews' => $reviews,
        ]);
    }
}
