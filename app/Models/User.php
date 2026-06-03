<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const ROLE_DOCTOR = 'doctor';
    public const ROLE_PATIENT = 'patient';
    public const ROLE_USER = 'user';

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'password',
        'role',
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
        $name = trim($this->first_name . ' ' . $this->last_name);

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
        return match ($this->role) {
            self::ROLE_DOCTOR => self::ROLE_DOCTOR,
            self::ROLE_PATIENT => self::ROLE_PATIENT,
            default => self::ROLE_USER,
        };
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
