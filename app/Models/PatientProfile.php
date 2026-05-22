<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        return $this->user?->displayName() ?? 'Paziente';
    }
}
