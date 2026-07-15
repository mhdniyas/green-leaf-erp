<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebsiteEnquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $search = $request->string('search')->toString();
        $source = $request->string('source')->toString();

        $enquiries = WebsiteEnquiry::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when(in_array($source, ['home', 'products'], true), function ($query) use ($source): void {
                $query->where('source_page', $source);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => WebsiteEnquiry::query()->count(),
            'today' => WebsiteEnquiry::query()->whereDate('created_at', today())->count(),
            'home' => WebsiteEnquiry::query()->where('source_page', 'home')->count(),
            'products' => WebsiteEnquiry::query()->where('source_page', 'products')->count(),
        ];

        return view('admin.enquiries.index', compact('enquiries', 'search', 'source', 'stats'));
    }
}
