<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrayMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'date',
        'tray_type_id',
        'sent_qty',
        'returned_qty',
    ];

    protected function casts(): array
    {
        return [
            'shop_id' => 'integer',
            'tray_type_id' => 'integer',
            'sent_qty' => 'integer',
            'returned_qty' => 'integer',
            'date' => 'date:Y-m-d',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function trayType(): BelongsTo
    {
        return $this->belongsTo(TrayType::class);
    }
}
