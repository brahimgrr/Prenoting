<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStatusHistory extends Model
{
  public const CREATED_AT = 'changed_at';
  public const UPDATED_AT = null;

  protected $table = 'appointment_status_history';

  protected $fillable = [
    'appointment_id',
    'previous_status',
    'new_status',
    'changed_by',
  ];

  public function appointment(): BelongsTo
  {
    return $this->belongsTo(Appointment::class);
  }

  public function changedBy(): BelongsTo
  {
    return $this->belongsTo(User::class, 'changed_by');
  }
}
