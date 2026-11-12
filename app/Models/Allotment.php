<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'performance_indicator_id',
    'sub_allotment_number',
    'year',
    'authorization_type',
])]
class Allotment extends Model
{
    public function performanceIndicator(): BelongsTo
    {
        return $this->belongsTo(PerformanceIndicator::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function allotmentEntries(): HasManyThrough
    {
        return $this->hasManyThrough(AllotmentEntry::class, Expense::class);
    }

    public function totalAmount(): float
    {
        return round((float) $this->expenses()->sum('amount'), 2);
    }

    public function utilizedAmount(): float
    {
        return round((float) $this->allotmentEntries()->sum('allotment_entries.amount'), 2);
    }

    public function remainingAmount(): float
    {
        return round($this->totalAmount() - $this->utilizedAmount(), 2);
    }

    protected function casts(): array
    {
        return [
            'performance_indicator_id' => 'integer',
            'year' => 'integer',
        ];
    }
}
