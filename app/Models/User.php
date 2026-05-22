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
    'username',
    'email',
    'first_name',
    'last_name',
    'password',
    'role',
  ];

  protected $hidden = [
    'password',
    'remember_token',
  ];

  protected function casts(): array
  {
    return [
      'password' => 'hashed',
    ];
  }

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

    return $name !== '' ? $name : $this->username;
  }

  public function portalRole(): string
  {
    return match ($this->role) {
      self::ROLE_DOCTOR => self::ROLE_DOCTOR,
      self::ROLE_PATIENT => self::ROLE_PATIENT,
      default => self::ROLE_USER,
    };
  }

  public function portalRoute(): ?string
  {
    return match ($this->portalRole()) {
      self::ROLE_DOCTOR => '/doctor/agenda',
      self::ROLE_PATIENT => '/patient',
      default => null,
    };
  }
}
