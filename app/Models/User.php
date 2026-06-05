<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    public const ROLE_DOCTOR = 'doctor';

    public const ROLE_PATIENT = 'patient';

    public const ROLE_USER = 'user';

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function displayName(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);

        return $name !== '' ? $name : $this->email;
    }

    public function portalRoute(): ?string
    {
        return match ($this->portalRole()) {
            self::ROLE_DOCTOR => '/doctor',
            self::ROLE_PATIENT => '/patient',
            default => null,
        };
    }

    public function portalRole(): string
    {
        if ($this->hasRole(self::ROLE_DOCTOR)) {
            return self::ROLE_DOCTOR;
        }

        if ($this->hasRole(self::ROLE_PATIENT)) {
            return self::ROLE_PATIENT;
        }

        return self::ROLE_USER;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
