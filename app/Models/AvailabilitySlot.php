<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilitySlot extends Model
{
  protected $fillable = [
    'doctor_id',
    'clinic_id',
    'start_at',
    'end_at',
    'is_blocked',
    'is_booked',
  ];

  protected function casts(): array
  {
    return [
      'start_at' => 'datetime',
      'end_at' => 'datetime',
      'is_blocked' => 'boolean',
      'is_booked' => 'boolean',
    ];
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_id');
  }

  public function clinic(): BelongsTo
  {
    return $this->belongsTo(ClinicLocation::class, 'clinic_id');
  }

  public function appointments(): HasMany
  {
    return $this->hasMany(Appointment::class, 'slot_id');
  }

  public function scopePublicAvailable(Builder $query): Builder
  {
    return $query
      ->where('is_blocked', false)
      ->where('is_booked', false)
      ->where('start_at', '>', now())
      ->whereHas('doctor', fn (Builder $doctor) => $doctor->where('is_active', true))
      ->whereHas('clinic', fn (Builder $clinic) => $clinic->where('is_active', true));
  }
}
