<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'target_id',
    'program_id',
    'office_id',
    'code',
    'description',
])]
class PerformanceIndicator extends Model
{
    protected static function booted(): void
    {
        static::saved(function (PerformanceIndicator $indicator): void {
            if (! $indicator->wasRecentlyCreated && ! $indicator->wasChanged(['target_id', 'program_id'])) {
                return;
            }

            $target = $indicator->target()->firstOrFail();
            $program = $indicator->program()->firstOrFail();
            $target->outcomeAreas()->syncWithoutDetaching([$program->outcome_area_id]);
            $target->programs()->syncWithoutDetaching([$program->id]);
        });
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(Allotment::class);
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(Accomplishment::class);
    }

    /** @return array{actual: ?float, target: ?float} */
    public function accomplishmentTotals(string $unit = 'number'): array
    {
        $records = $this->accomplishments->where('unit', $unit);
        $numericValue = static function (mixed $value) use ($unit): ?float {
            if ($unit === 'percentage' && is_string($value)) {
                $value = preg_replace('/%$/', '', trim($value));
            }

            return is_numeric($value) ? (float) $value : null;
        };
        $actuals = $records->map(fn (Accomplishment $record): ?float => $numericValue($record->actual))->filter(fn (?float $value): bool => $value !== null);
        $targets = $records->map(fn (Accomplishment $record): ?float => $numericValue($record->target))->filter(fn (?float $value): bool => $value !== null);

        return [
            'actual' => $actuals->isEmpty() ? null : round((float) ($unit === 'percentage' ? $actuals->avg() : $actuals->sum()), 2),
            'target' => $targets->isEmpty() ? null : round((float) ($unit === 'percentage' ? $targets->avg() : $targets->sum()), 2),
        ];
    }

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'program_id' => 'integer',
            'office_id' => 'integer',
        ];
    }
}
