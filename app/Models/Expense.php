<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'allotment_id',
    'uacs_id',
    'amount',
])]
class Expense extends Model
{
    public function allotment(): BelongsTo
    {
        return $this->belongsTo(Allotment::class);
    }

    public function uacs(): BelongsTo
    {
        return $this->belongsTo(Uacs::class);
    }

    public function allotmentEntries(): HasMany
    {
        return $this->hasMany(AllotmentEntry::class);
    }

    public function utilizedAmount(?AllotmentEntry $exceptEntry = null): float
    {
        $entries = $this->allotmentEntries();

        if ($exceptEntry?->exists) {
            $entries->whereKeyNot($exceptEntry->getKey());
        }

        return round((float) $entries->sum('amount'), 2);
    }

    public function remainingAmount(?AllotmentEntry $exceptEntry = null): float
    {
        return round((float) $this->amount - $this->utilizedAmount($exceptEntry), 2);
    }

    public function utilizedPercentage(): float
    {
        $amount = (float) $this->amount;

        if ($amount <= 0) {
            return 0;
        }

        return round(($this->utilizedAmount() / $amount) * 100, 1);
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if ($this->exists) {
                self::query()->whereKey($this->getKey())->lockForUpdate()->first();
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense): void {
            if (! $expense->exists || $expense->amount === null) {
                return;
            }

            $utilizedAmount = $expense->allotmentEntries()->sum('amount');

            if ((float) $expense->amount >= (float) $utilizedAmount) {
                return;
            }

            $message = sprintf(
                'The expense amount cannot be less than its utilized amount of PHP %s.',
                number_format((float) $utilizedAmount, 2),
            );

            Notification::make()
                ->danger()
                ->title('Expense amount exceeded')
                ->body($message)
                ->send();

            throw ValidationException::withMessages([
                'amount' => $message,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'allotment_id' => 'integer',
            'uacs_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }
}
