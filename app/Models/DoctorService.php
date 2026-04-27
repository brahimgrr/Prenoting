<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorService extends Model
{
  protected $fillable = ['doctor_id', 'service_id'];

  public function doctor(): BelongsTo
  {
    return $this->belongsTo(DoctorProfile::class, 'doctor_id');
  }

  public function service(): BelongsTo
  {
    return $this->belongsTo(MedicalService::class, 'service_id');
  }
}
