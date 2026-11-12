<?php

namespace App\Livewire\Filament\App;

use App\Models\Allotment;
use App\Models\Expense;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;

class AllotmentEntriesTable extends TableComponent
{
    public Allotment $allotment;

    public function mount(Allotment $allotment): void
    {
        $this->allotment = $allotment;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->allotment->expenses()->getQuery()
                ->with('uacs')
                ->withSum('allotmentEntries as utilized_amount', 'amount'))
            ->columns([
                TextColumn::make('uacs.code')
                    ->label('UACS code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uacs.description')
                    ->label('Type of expense')
                    ->wrap(),
                TextColumn::make('amount')
                    ->label('Expense amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total allotment amount')->money('PHP')),
                TextColumn::make('utilized_amount')
                    ->label('Utilized amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->summarize(Sum::make()->label('Total utilized amount')->money('PHP')),
                TextColumn::make('utilized_percentage')
                    ->label('Utilization')
                    ->alignEnd()
                    ->state(function (Expense $record): float {
                        $expenseAmount = (float) $record->amount;

                        return $expenseAmount > 0
                            ? round(((float) $record->utilized_amount / $expenseAmount) * 100, 1)
                            : 0;
                    })
                    ->formatStateUsing(fn (float $state): string => number_format($state, 1).'%')
                    ->badge()
                    ->color(function (Expense $record): string {
                        $expenseAmount = (float) $record->amount;
                        $percentage = $expenseAmount > 0
                            ? ((float) $record->utilized_amount / $expenseAmount) * 100
                            : 0;

                        return match (true) {
                            $percentage >= 85 => 'success',
                            $percentage >= 50 => 'warning',
                            $percentage >= 1 => 'danger',
                            default => 'gray',
                        };
                    })
                    ->summarize(Summarizer::make()
                        ->label('Overall utilization')
                        ->using(function (QueryBuilder $query): float {
                            $totalExpense = (float) (clone $query)->sum('amount');
                            $totalUtilized = (float) (clone $query)->sum('utilized_amount');

                            return $totalExpense > 0
                                ? round(($totalUtilized / $totalExpense) * 100, 1)
                                : 0;
                        })
                        ->formatStateUsing(fn (float $state): string => number_format($state, 1).'%')),
            ])
            ->defaultSort('uacs.code')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No expense types')
            ->emptyStateDescription('This allotment does not have any expense types yet.');
    }

    public function render(): View
    {
        return view('livewire.filament.app.allotment-entries-table');
    }
}
