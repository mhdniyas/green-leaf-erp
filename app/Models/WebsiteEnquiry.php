<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebsiteEnquiryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteEnquiry extends Model
{
    /** @use HasFactory<WebsiteEnquiryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'customer_type',
        'required_date',
        'message',
        'source_page',
    ];

    protected $casts = [
        'required_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
