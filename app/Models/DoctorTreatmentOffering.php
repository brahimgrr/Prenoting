<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorTreatmentOffering extends Model
{
  protected $fillable = [
    'doctor_id',
    'name',
    'category',
    'specialty_id',
    'duration_minutes',
    'price',
    'is_active',
  ];

  protected function casts(): array
  {
    return [
      'duration_minutes' => 'integer',
      'price' => 'decimal:2',
      'is_active' => 'boolean',
    ];
  }

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_id');
  }

  public function specialty(): BelongsTo
  {
    return $this->belongsTo(Specialty::class);
  }
}
