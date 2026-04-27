<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MedicalService extends Model
{
  public const CATEGORY_VISIT = 'visit';
  public const CATEGORY_EXAM = 'exam';

  protected $fillable = [
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

  public function specialty(): BelongsTo
  {
    return $this->belongsTo(Specialty::class);
  }

  public function doctors(): BelongsToMany
  {
    return $this->belongsToMany(DoctorProfile::class, 'doctor_services', 'service_id', 'doctor_id')
      ->withTimestamps();
  }
}
