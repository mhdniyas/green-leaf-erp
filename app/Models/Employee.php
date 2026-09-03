<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'user_id',
        'default_shop_id',
        'employee_category_id',
        'name',
        'phone',
        'alternate_phone',
        'email',
        'photo_path',
        'id_type',
        'other_id_type',
        'id_number',
        'id_front_path',
        'id_back_path',
        'address',
        'staff_area',
        'employment_status',
        'verification_status',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'joined_on',
        'monthly_salary',
        'salary_type',
        'daily_wage',
        'is_user_linked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'reviewed_at' => 'datetime',
            'monthly_salary' => 'decimal:2',
            'daily_wage' => 'decimal:2',
            'is_user_linked' => 'boolean',
        ];
    }

    public function getEmergencyContactNumberAttribute(): ?string
    {
        return $this->alternate_phone;
    }

    public function getRouteKeyName(): string
    {
        return 'employee_code';
    }

    public static function generateNextCode(): string
    {
        $maxId = (int) static::query()->max('id') + 1;

        return 'EMP-'.str_pad((string) $maxId, 5, '0', STR_PAD_LEFT);
    }

    public function getFormattedIdTypeAttribute(): string
    {
        if (! $this->id_type) {
            return 'ID Proof';
        }

        return match ($this->id_type) {
            'aadhaar' => 'Aadhaar',
            'passport' => 'Passport',
            'driving_licence' => 'Driving Licence',
            'voter_id' => 'Voter ID',
            'pan' => 'PAN',
            'other' => $this->other_id_type ?: 'Other ID',
            default => str($this->id_type)->headline()->toString(),
        };
    }

    public function getMaskedIdNumberAttribute(): ?string
    {
        if (! $this->id_number) {
            return null;
        }

        $typeLabel = $this->formatted_id_type;
        $num = trim($this->id_number);
        $last4 = strlen($num) >= 4 ? substr($num, -4) : $num;

        return "{$typeLabel} •••• {$last4}";
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return Storage::url($this->photo_path);
    }

    public function getIdFrontUrlAttribute(): ?string
    {
        if (! $this->id_front_path) {
            return null;
        }

        return Storage::url($this->id_front_path);
    }

    public function getIdBackUrlAttribute(): ?string
    {
        if (! $this->id_back_path) {
            return null;
        }

        return Storage::url($this->id_back_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultShop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'default_shop_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EmployeeCategory::class, 'employee_category_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRequest::class);
    }

    public function leaveLedgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
    }

    public function hrOverrides(): HasMany
    {
        return $this->hasMany(HrOverride::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function payrollPayments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function shopStaffPayments(): HasMany
    {
        return $this->hasMany(ShopStaffPayment::class);
    }

    public function advanceRequests(): HasMany
    {
        return $this->hasMany(EmployeeAdvanceRequest::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }

    public function assignedShops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_employee_assignments')
            ->withTimestamps();
    }

    public function shopAssignments(): HasMany
    {
        return $this->hasMany(ShopEmployeeAssignment::class);
    }
}
