<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'organization_name',
    'accounts_domain',
    'api_domain',
    'data_center',
    'access_token',
    'refresh_token',
    'access_token_expires_at',
    'scopes',
    'status',
    'connected_by',
    'connected_at',
])]
#[Hidden([
    'access_token',
    'refresh_token',
])]
class ZohoBooksConnection extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * @return HasMany<ZohoBooksAccountMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(ZohoBooksAccountMapping::class, 'zoho_books_connection_id');
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && ! empty($this->refresh_token);
    }

    public function hasAccountantsReadScope(): bool
    {
        if (empty($this->scopes) || ! is_array($this->scopes)) {
            return false;
        }

        foreach ($this->scopes as $scope) {
            $trimmed = trim((string) $scope);
            if ($trimmed === 'ZohoBooks.accountants.READ' || $trimmed === 'ZohoBooks.fullaccess.READ' || $trimmed === 'ZohoBooks.fullaccess.ALL') {
                return true;
            }
        }

        return false;
    }

    public function isAccessTokenExpired(int $safetyBufferSeconds = 60): bool
    {
        if (! $this->access_token || ! $this->access_token_expires_at) {
            return true;
        }

        return $this->access_token_expires_at->subSeconds($safetyBufferSeconds)->isPast();
    }
}
