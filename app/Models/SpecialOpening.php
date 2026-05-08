<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialOpening extends Model
{
  protected $fillable = [
    'doctor_profile_id',
    'date',
    'start_time',
    'end_time',
    'note',
  ];

  protected function casts(): array
  {
    return [
      'date' => 'date',
    ];
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
  }
}
