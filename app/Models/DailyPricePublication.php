<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DailyPricePublication extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_date',
        'is_published',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'business_date' => 'date',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public static function isPublishedForDate(string|Carbon $date): bool
    {
        $dateStr = Carbon::parse($date)->toDateString();

        return (bool) static::query()
            ->whereDate('business_date', $dateStr)
            ->where('is_published', true)
            ->value('is_published');
    }

    public static function setPublishStatus(string|Carbon $date, bool $isPublished, ?User $user = null): self
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $record = static::query()->whereDate('business_date', $dateStr)->first();

        if ($record) {
            $record->update([
                'is_published' => $isPublished,
                'published_at' => $isPublished ? now() : null,
                'published_by' => $isPublished ? ($user?->id ?? auth()->id()) : null,
            ]);

            return $record;
        }

        return static::query()->create([
            'business_date' => $dateStr,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
            'published_by' => $isPublished ? ($user?->id ?? auth()->id()) : null,
        ]);
    }
}
