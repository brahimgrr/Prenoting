<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicLocation extends Model
{
  protected $fillable = ['name', 'address', 'phone', 'is_active'];

  protected function casts(): array
  {
    return [
      'is_active' => 'boolean',
    ];
  }

  public function availabilitySlots(): HasMany
  {
    return $this->hasMany(AvailabilitySlot::class, 'clinic_id');
  }
}
