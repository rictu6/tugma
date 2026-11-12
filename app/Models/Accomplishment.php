<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;

#[Fillable([
    'performance_indicator_id',
    'type',
    'unit',
    'target_quarter',
    'target_semi_annual',
    'target',
    'date_accomplished',
    'actual',
    'tag',
    'actual_accomplishment_description',
    'reason',
    'remarks',
])]
class Accomplishment extends Model
{
    /** @return array<string, string> */
    public static function tagOptions(): array
    {
        return [
            'met' => 'Met',
            'unmet' => 'Unmet',
            'cont' => 'Continuing',
            'deferred' => 'Deferred',
            'moved' => 'Moved',
            'na' => 'N/A',
            'exceeded' => 'Exceeded',
        ];
    }

    public function performanceIndicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'fixed' => 'Fixed',
            'recurring_quarterly' => 'Recurring Quarterly',
            'recurring_semestral' => 'Recurring Semestral',
            'cumulative' => 'Cumulative',
        ];
    }

    public static function defaultPeriodCountForType(?string $type): int
    {
        return match ($type) {
            'recurring_quarterly', 'cumulative' => 4,
            'recurring_semestral' => 2,
            default => 1,
        };
    }

    public function performanceStatus(): string
    {
        $targetYear = $this->targetYear();

        if (! $this->date_accomplished || ! $this->target_quarter || ! $targetYear) {
            return 'Pending';
        }

        $targetPeriod = ($targetYear * 4) + $this->target_quarter;
        $accomplishedPeriod = ($this->date_accomplished->year * 4) + $this->date_accomplished->quarter;

        return match (true) {
            $accomplishedPeriod < $targetPeriod => 'Ahead',
            $accomplishedPeriod > $targetPeriod => 'Late',
            default => 'On Time',
        };
    }

    public function actualAsNumber(): ?float
    {
        if ($this->unit !== 'number') {
            return null;
        }

        return $this->numericActualValue();
    }

    public function actualTargetComparison(): ?int
    {
        $actual = $this->actual;
        $target = $this->target;

        if ($this->unit === 'percentage') {
            $actual = is_string($actual) ? preg_replace('/%$/', '', trim($actual)) : $actual;
            $target = is_string($target) ? preg_replace('/%$/', '', trim($target)) : $target;
        }

        if (! is_numeric($actual) || ! is_numeric($target)) {
            return null;
        }

        return (float) $actual <=> (float) $target;
    }

    public function cumulativeActual(): ?float
    {
        if ($this->type !== 'cumulative' || $this->numericActualValue() === null) {
            return null;
        }

        $performanceIndicator = $this->performanceIndicator;

        if (! $performanceIndicator) {
            return $this->numericActualValue();
        }

        /** @var Collection<int, self> $accomplishments */
        $accomplishments = $performanceIndicator->relationLoaded('accomplishments')
            ? $performanceIndicator->accomplishments
            : $performanceIndicator->accomplishments()
                ->where('type', 'cumulative')
                ->where('unit', $this->unit)
                ->where('target_quarter', '<=', $this->target_quarter)
                ->get();

        return round((float) $accomplishments
            ->where('type', 'cumulative')
            ->where('unit', $this->unit)
            ->where('target_quarter', '<=', $this->target_quarter)
            ->sum(fn (self $accomplishment): float => $accomplishment->numericActualValue() ?? 0), 2);
    }

    private function numericActualValue(): ?float
    {
        return is_numeric($this->actual) ? (float) $this->actual : null;
    }

    public function performanceStatusColor(): string
    {
        return match ($this->performanceStatus()) {
            'On Time' => 'primary',
            'Ahead' => 'success',
            'Late' => 'danger',
            default => 'gray',
        };
    }

    private function targetYear(): ?int
    {
        $year = $this->performanceIndicator?->target?->fiscalYear?->year;

        return $year === null ? null : (int) $year;
    }

    protected function casts(): array
    {
        return [
            'performance_indicator_id' => 'integer',
            'target_quarter' => 'integer',
            'target_semi_annual' => 'integer',
            'date_accomplished' => 'date',
        ];
    }
}
