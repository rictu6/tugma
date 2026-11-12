<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'outcome_area_id',
    'short_code',
    'name',
    'description',
    'is_active',
])]
class Program extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Program $program): void {
            if ($program->isDirty('outcome_area_id')) {
                throw ValidationException::withMessages(['outcome_area_id' => 'A program cannot be moved to another outcome area.']);
            }
        });
    }

    public function outcomeArea(): BelongsTo
    {
        return $this->belongsTo(OutcomeArea::class);
    }

    public function performanceIndicators(): HasMany
    {
        return $this->hasMany(PerformanceIndicator::class);
    }

    protected function casts(): array
    {
        return [
            'outcome_area_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
