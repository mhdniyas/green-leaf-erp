<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopEmployeeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopEmployeeAssignment extends Model
{
    /** @use HasFactory<ShopEmployeeAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'employee_id',
        'assigned_by',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
