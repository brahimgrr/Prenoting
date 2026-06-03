<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class DoctorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'license_number',
        'phone',
        'clinic_address',
    ];

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

    public function medicalServices(): HasMany
    {
        return $this->hasMany(MedicalService::class, 'doctor_profile_id');
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            MedicalService::class,
            'doctor_profile_id',
            'service_id',
            'id',
            'id',
        );
    }
}
