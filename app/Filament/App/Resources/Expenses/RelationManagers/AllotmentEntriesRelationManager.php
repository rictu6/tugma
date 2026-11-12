<?php

namespace App\Filament\App\Resources\Expenses\RelationManagers;

use App\Models\AllotmentEntry;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

class AllotmentEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'allotmentEntries';

    protected static ?string $recordTitleAttribute = 'entry_date';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && Gate::allows('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('performance_indicator_code')
                    ->label('Reference (PR/PO No.)')
                    ->maxLength(255),
                DatePicker::make('entry_date')
                    ->required()
                    ->unique(modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('expense_id', $this->getOwnerRecord()->getKey())),
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
            ->recordTitleAttribute('entry_date')
            ->columns([
                TextColumn::make('entry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('performance_indicator_code')
                    ->label('Reference (PR/PO No.)')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
