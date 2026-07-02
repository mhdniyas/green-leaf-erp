<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopOwnerAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOwnerAssignment extends Model
{
    /** @use HasFactory<ShopOwnerAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
