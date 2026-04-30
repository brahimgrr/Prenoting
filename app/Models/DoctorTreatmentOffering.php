<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorTreatmentOffering extends Model
{
  protected $fillable = [
    'name',
    'category',
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

}
