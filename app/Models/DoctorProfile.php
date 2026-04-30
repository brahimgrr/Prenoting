<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorProfile extends Model
{
  protected $fillable = [
    'user_id',
    'display_name',
    'bio',
    'license_number',
    'is_active',
  ];

  protected function casts(): array
  {
    return [
      'is_active' => 'boolean',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

}
