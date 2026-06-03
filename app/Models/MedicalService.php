<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalService extends Model
{
    public const CATEGORY_VISIT = 'VISITA';
    public const CATEGORY_EXAM = 'ESAME';

    protected $fillable = [
        'doctor_profile_id',
        'name',
        'category',
        'duration_minutes',
        'price',
        'is_active',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(DoctorProfile::class, 'doctor_profile_id');
    }

    protected function casts(): array
    {
        return [
            'doctor_profile_id' => 'integer',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

}
