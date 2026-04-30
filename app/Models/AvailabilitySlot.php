<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilitySlot extends Model
{
  protected $fillable = [
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

  public function appointments(): HasMany
  {
    return $this->hasMany(Appointment::class, 'slot_id');
  }

  public function scopePublicAvailable(Builder $query): Builder
  {
    return $query
      ->where('is_blocked', false)
      ->where('is_booked', false)
      ->where('start_at', '>', now());
  }
}
