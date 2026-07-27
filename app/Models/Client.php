<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'status',
        'notes',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
