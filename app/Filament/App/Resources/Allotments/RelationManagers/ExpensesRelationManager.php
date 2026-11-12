<?php

namespace App\Filament\App\Resources\Allotments\RelationManagers;

use App\Filament\App\Resources\Expenses\ExpenseResource;
use App\Models\Allotment;
use App\Models\Expense;
use App\Models\Uacs;
use Dom\Text;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $recordTitleAttribute = 'uacs.code';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && Gate::allows('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('uacs_id')
                    ->relationship('uacs', 'code')
                    ->getOptionLabelFromRecordUsing(fn(Uacs $record): string => $record->code . ' — ' . $record->description)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->unique(modifyRuleUsing: fn(Unique $rule): Unique => $rule
                        ->where('allotment_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->prefix('₱'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query
                ->withSum('allotmentEntries as utilized_amount', 'amount'))
            ->recordTitleAttribute('uacs.code')
            ->columns([
                TextColumn::make('uacs.code')
                    ->label('UACS')
                    ->description(fn(Expense $record): ?string => $record->uacs?->description)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Expense Amount')
                    ->alignEnd()
                    ->money('PHP')
                    ->sortable()
                    ->summarize(Sum::make('amount')->label('Total allotment amount')->money('PHP')),
                TextColumn::make('utilized_amount')
                    ->label('Amount Utilized')
                    ->alignEnd()
                    ->money('PHP')
                    ->placeholder('₱0.00')
                    ->summarize(Sum::make('utilized_amount')->label('Total utilized amount')->money('PHP')),
                TextColumn::make('remaining_amount')
                    ->alignEnd()
                    ->label('Remaining')
                    ->state(fn(Expense $record): float => $record->remainingAmount())
                    ->money('PHP'),
                TextColumn::make('utilized_percentage')
                    ->label('Utilized %')
                    ->alignEnd()
                    ->getStateUsing(fn(Expense $record): float => $record->utilizedPercentage())
                    ->suffix('%')
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
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewEntries')
                    ->label('Breakdown')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->authorize(fn(Expense $record): bool => Gate::allows('view', $record))
                    ->modalHeading(fn(Expense $record): string => 'Entries for ' . ($record->uacs?->code . ' — ' . $record->uacs?->description ?? 'expense'))
                    ->modalWidth('5xl')
                    ->schema(fn(Expense $record): array => [
                        Section::make('Expense summary')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('amount')
                                    ->label('Total amount')
                                    ->state(fn(Expense $record): float => (float) $record->amount)
                                    ->money('PHP'),
                                TextEntry::make('remaining_amount')
                                    ->label('Remaining amount')
                                    ->state(fn(Expense $record): float => $record->remainingAmount())
                                    ->money('PHP'),
                                TextEntry::make('utilized_amount')
                                    ->label('Utilized amount')
                                    ->state(fn(Expense $record): float => $record->utilizedAmount())
                                    ->money('PHP'),
                                TextEntry::make('utilized_percentage')
                                    ->label('Utilized percentage')
                                    ->state(fn(Expense $record): float => $record->utilizedPercentage())
                                    ->suffix('%')
                            ]),
                        SchemaView::make('filament.app.resources.expenses.relation-managers.expense-entries-table')
                            ->viewData(['expense' => $record])
                            ->columnSpanFull(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
