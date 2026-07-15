<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreWebsiteEnquiryRequest;
use App\Models\WebsiteEnquiry;
use Illuminate\Http\RedirectResponse;

class WebsiteEnquiryController extends Controller
{
    public function store(StoreWebsiteEnquiryRequest $request): RedirectResponse
    {
        $enquiry = WebsiteEnquiry::create($request->validated());

        $redirectRoute = $enquiry->source_page === 'products'
            ? 'products.index'
            : 'home';

        return redirect()
            ->to(route($redirectRoute).'#contact')
            ->with('success', 'Enquiry received. Our team will contact you shortly.');
    }
}
