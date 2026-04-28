<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorProfile extends Model
{
  protected $fillable = [
    'user_id',
    'display_name',
    'specialty_id',
    'bio',
    'license_number',
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

  public function specialty(): BelongsTo
  {
    return $this->belongsTo(Specialty::class);
  }

  public function services(): BelongsToMany
  {
    return $this->belongsToMany(MedicalService::class, 'doctor_services', 'doctor_id', 'service_id')
      ->withTimestamps();
  }

  public function availabilitySlots(): HasMany
  {
    return $this->hasMany(AvailabilitySlot::class, 'doctor_id');
  }

  public function treatmentOfferings(): HasMany
  {
    return $this->hasMany(DoctorTreatmentOffering::class, 'doctor_id');
  }

  public function appointments(): HasMany
  {
    return $this->hasMany(Appointment::class, 'doctor_id');
  }
}
