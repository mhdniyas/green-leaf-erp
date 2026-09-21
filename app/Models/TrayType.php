<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrayType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'total_owned',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_owned' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TrayMovement::class);
    }

    /**
     * Total trays currently with shops (all sent - all returned).
     */
    public function getWithShopsAttribute(): int
    {
        $sent = (int) $this->movements()->sum('sent_qty');
        $returned = (int) $this->movements()->sum('returned_qty');

        return max(0, $sent - $returned);
    }

    /**
     * Total trays currently available in warehouse (total_owned - with_shops).
     */
    public function getInWarehouseAttribute(): int
    {
        return (int) $this->total_owned - $this->with_shops;
    }
}
