<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
  public const PORTAL_RELATIONS = ['patient.user', 'service', 'doctor'];

  public const CANCELLED_BY_PATIENT = 'patient';
  public const CANCELLED_BY_DOCTOR = 'doctor';
  public const CANCELLED_BY_SYSTEM = 'system';

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
    'doctor_profile_id',
    'service_id',
    'start_at',
    'end_at',
    'status',
    'notes',
    'cancellation_reason',
    'cancelled_by_role',
    'cancelled_by_user_id',
    'cancelled_at',
  ];

  protected function casts(): array
  {
    return [
      'start_at' => 'datetime',
      'end_at' => 'datetime',
      'cancelled_at' => 'datetime',
    ];
  }

  public function patient(): BelongsTo
  {
    return $this->belongsTo(PatientProfile::class, 'patient_id');
  }

  public function service(): BelongsTo
  {
    return $this->belongsTo(MedicalService::class, 'service_id');
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
  }

  public function cancelledByUser(): BelongsTo
  {
    return $this->belongsTo(User::class, 'cancelled_by_user_id');
  }

  public function scopeWithPortalRelations(Builder $query): Builder
  {
    return $query->with(self::PORTAL_RELATIONS);
  }

  public function scopeActiveSlot(Builder $query): Builder
  {
    return $query->whereIn('status', self::ACTIVE_SLOT_STATUSES);
  }

  public function scopeFutureActiveSlot(Builder $query): Builder
  {
    return $query->activeSlot()->where('start_at', '>=', now());
  }

  public function patientName(): string
  {
    return $this->patient?->displayName() ?? 'Paziente';
  }

  public function isFutureConfirmed(): bool
  {
    return $this->status === self::STATUS_CONFIRMED && $this->start_at->isFuture();
  }

  public function cancellationActorLabel(): string
  {
    return match ($this->cancelled_by_role) {
      self::CANCELLED_BY_PATIENT => 'Annullato da te',
      self::CANCELLED_BY_DOCTOR => 'Annullato dal medico',
      default => 'Annullato',
    };
  }
}
