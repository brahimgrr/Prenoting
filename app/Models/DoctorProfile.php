<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'license_number',
        'phone',
        'clinic_address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class, 'doctor_profile_id');
    }

    public function specialOpenings(): HasMany
    {
        return $this->hasMany(SpecialOpening::class, 'doctor_profile_id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(ScheduleClosure::class, 'doctor_profile_id');
    }
}
