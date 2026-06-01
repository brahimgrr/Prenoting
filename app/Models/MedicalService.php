<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicalService extends Model
{
    public const CATEGORY_VISIT = 'VISITA';
    public const CATEGORY_EXAM = 'ESAME';

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
