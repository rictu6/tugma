<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'expense_id',
    'performance_indicator_code',
    'entry_date',
    'amount',
])]
class AllotmentEntry extends Model
{
    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if (filled($this->expense_id)) {
                Expense::query()
                    ->whereKey($this->expense_id)
                    ->lockForUpdate()
                    ->first();
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            if (blank($entry->expense_id) || $entry->amount === null) {
                return;
            }

            $expense = Expense::query()->find($entry->expense_id);

            if (! $expense) {
                return;
            }

            $remainingAmount = $expense->remainingAmount($entry->exists ? $entry : null);
            $requestedAmount = round((float) $entry->amount, 2);

            if ($requestedAmount > $remainingAmount) {
                $message = sprintf(
                    'The requested amount exceeds the remaining expense amount of PHP %s.',
                    number_format($remainingAmount, 2),
                );

                Notification::make()
                    ->danger()
                    ->title('Expense amount exceeded')
                    ->body($message)
                    ->send();

                throw ValidationException::withMessages([
                    'amount' => $message,
                ]);
            }
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    protected function casts(): array
    {
        return [
            'expense_id' => 'integer',
            'entry_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }
}
