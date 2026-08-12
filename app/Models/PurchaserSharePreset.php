<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaserSharePreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'purchase_grade',
        'name',
        'product_ids',
    ];

    protected function casts(): array
    {
        return [
            'product_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
