<?php

namespace App\Livewire\Filament\App;

use App\Models\Expense;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\TableComponent;
use Illuminate\Contracts\View\View;

class ExpenseEntriesTable extends TableComponent
{
    public Expense $expense;

    public function mount(Expense $expense): void
    {
        $this->expense = $expense;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->expense->allotmentEntries()->getQuery())
            ->columns([
                TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('performance_indicator_code')
                    ->label('Description')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->label('Utilized amount')
                    ->alignEnd()
                    ->money('PHP')
                    ->sortable()
                    ->summarize(Sum::make()->label('Utilized total')->money('PHP')),
            ])
            ->defaultSort('entry_date')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No allotment entries')
            ->emptyStateDescription('This expense type does not have any entries yet.');
    }

    public function render(): View
    {
        return view('livewire.filament.app.expense-entries-table');
    }
}
