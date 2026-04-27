<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
  public const STATUS_CONFIRMED = 'confirmed';
  public const STATUS_CHECKED_IN = 'checked_in';
  public const STATUS_COMPLETED = 'completed';
  public const STATUS_CANCELLED = 'cancelled';
  public const STATUS_NO_SHOW = 'no_show';

  public const ACTIVE_SLOT_STATUSES = [
    self::STATUS_CONFIRMED,
    self::STATUS_CHECKED_IN,
  ];

  public const ALL_STATUSES = [
    self::STATUS_CONFIRMED,
    self::STATUS_CHECKED_IN,
    self::STATUS_COMPLETED,
    self::STATUS_CANCELLED,
    self::STATUS_NO_SHOW,
  ];

  protected $fillable = [
    'patient_id',
    'doctor_id',
    'service_id',
    'clinic_id',
    'slot_id',
    'start_at',
    'end_at',
    'status',
    'notes',
    'cancellation_reason',
  ];

  protected function casts(): array
  {
    return [
      'start_at' => 'datetime',
      'end_at' => 'datetime',
    ];
  }

  public function patient(): BelongsTo
  {
    return $this->belongsTo(PatientProfile::class, 'patient_id');
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_id');
  }

  public function service(): BelongsTo
  {
    return $this->belongsTo(MedicalService::class, 'service_id');
  }

  public function clinic(): BelongsTo
  {
    return $this->belongsTo(ClinicLocation::class, 'clinic_id');
  }

  public function slot(): BelongsTo
  {
    return $this->belongsTo(AvailabilitySlot::class, 'slot_id');
  }

  public function statusHistory(): HasMany
  {
    return $this->hasMany(AppointmentStatusHistory::class);
  }

  public function scopeWithPortalRelations(Builder $query): Builder
  {
    return $query->with(['patient.user', 'doctor.user', 'service', 'clinic', 'slot']);
  }

  public function patientName(): string
  {
    return $this->patient?->displayName() ?? 'Paziente';
  }

  public function isFutureConfirmed(): bool
  {
    return $this->status === self::STATUS_CONFIRMED && $this->start_at->isFuture();
  }
}
