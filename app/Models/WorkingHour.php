<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingHour extends Model
{
  protected $fillable = [
    'doctor_profile_id',
    'weekday',
    'start_time',
    'end_time',
    'effective_from',
    'effective_until',
    'is_active',
  ];

  protected function casts(): array
  {
    return [
      'weekday' => 'integer',
      'effective_from' => 'date',
      'effective_until' => 'date',
      'is_active' => 'boolean',
    ];
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
  }
}
