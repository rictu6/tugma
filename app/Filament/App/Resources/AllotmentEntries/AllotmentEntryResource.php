<?php

namespace App\Filament\App\Resources\AllotmentEntries;

use App\Filament\App\Resources\AllotmentEntries\Pages\ManageAllotmentEntries;
use App\Models\Allotment;
use App\Models\AllotmentEntry;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class AllotmentEntryResource extends Resource
{
    protected static ?string $model = AllotmentEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $recordTitleAttribute = 'entry_date';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('allotment_id')
                    ->label('Allotment')
                    ->afterStateHydrated(fn (Select $component, ?AllotmentEntry $record) => $component->state($record?->expense?->allotment_id))
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('expense_id', null))
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Allotment::query()
                        ->with('performanceIndicator')
                        ->where(function ($query) use ($search): void {
                            $query
                                ->where('sub_allotment_number', 'like', "%{$search}%")
                                ->orWhereHas('performanceIndicator', fn ($query) => $query->where('code', 'like', "%{$search}%"));
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Allotment $record): array => [
                            $record->getKey() => $record->sub_allotment_number.' — '.$record->performanceIndicator->code.' ('.$record->year.')',
                        ])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($allotment = Allotment::query()->with('performanceIndicator')->find($value))
                        ? $allotment->sub_allotment_number.' — '.$allotment->performanceIndicator->code.' ('.$allotment->year.')'
                        : null),
                Select::make('expense_id')
                    ->label('Expense (UACS)')
                    ->options(fn (Get $get): array => Expense::query()
                        ->where('allotment_id', $get('allotment_id'))
                        ->with('uacs')
                        ->get()
                        ->mapWithKeys(fn (Expense $expense): array => [
                            $expense->getKey() => $expense->uacs->code.' — '.$expense->uacs->description,
                        ])
                        ->all())
                    ->required()
                    ->searchable()
                    ->disabled(fn (Get $get): bool => blank($get('allotment_id'))),
                TextInput::make('performance_indicator_code')
                    ->label('Description')
                    ->maxLength(255),
                DatePicker::make('entry_date')
                    ->required()
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('expense_id', $get('expense_id'))),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step('0.01')
                    ->prefix('₱'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entry_date')
            ->columns([
                TextColumn::make('expense.allotment.sub_allotment_number')
                    ->label('Sub-allotment')
                    ->description(fn (AllotmentEntry $record): ?string => $record->expense?->allotment?->performanceIndicator?->code)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expense.uacs.code')
                    ->label('UACS Code')
                    ->description(fn (AllotmentEntry $record): ?string => $record->expense?->uacs?->description)
                    ->searchable(),
                TextColumn::make('performance_indicator_code')
                    ->label('Description')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAllotmentEntries::route('/'),
        ];
    }
}
