<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'phone',
        'address',
        'codice_fiscale',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function displayName(): string
    {
        return $this->user?->displayName() ?? 'Paziente';
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }
}
