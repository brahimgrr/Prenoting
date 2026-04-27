<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
  protected $fillable = ['name', 'description'];

  public function doctors(): HasMany
  {
    return $this->hasMany(DoctorProfile::class);
  }

  public function services(): HasMany
  {
    return $this->hasMany(MedicalService::class);
  }
}
