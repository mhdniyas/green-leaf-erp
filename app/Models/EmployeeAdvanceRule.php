<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeAdvanceRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAdvanceRule extends Model
{
    /** @use HasFactory<EmployeeAdvanceRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'minimum_present_days',
        'advance_percent',
        'default_from_petty_cash',
        'allow_negative_shop_balance',
        'is_active',
        'notes',
    ];

    protected $attributes = [
        'name' => 'Default advance rule',
        'minimum_present_days' => 20,
        'advance_percent' => 50,
        'default_from_petty_cash' => true,
        'allow_negative_shop_balance' => true,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'minimum_present_days' => 'integer',
            'advance_percent' => 'decimal:2',
            'default_from_petty_cash' => 'boolean',
            'allow_negative_shop_balance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRequest::class);
    }

    public static function activeRule(): self
    {
        return self::query()->where('is_active', true)->latest('id')->first()
            ?? self::query()->create();
    }
}
