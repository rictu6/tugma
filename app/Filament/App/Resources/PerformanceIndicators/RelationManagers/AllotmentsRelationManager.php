<?php

namespace App\Filament\App\Resources\PerformanceIndicators\RelationManagers;

use App\Models\Allotment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

class AllotmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'allotments';

    protected static ?string $recordTitleAttribute = 'sub_allotment_number';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && Gate::allows('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sub_allotment_number')
                    ->required()
                    ->maxLength(255)
                    ->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('performance_indicator_id', $this->getOwnerRecord()->getKey())),
                TextInput::make('year')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535),
                TextInput::make('authorization_type')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withSum('expenses as total_amount', 'amount'))
            ->recordTitleAttribute('sub_allotment_number')
            ->columns([
                TextColumn::make('sub_allotment_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('authorization_type')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Total amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->default(0)
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('viewEntries')
                    ->label('Breakdown')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->authorize(fn (Allotment $record): bool => Gate::allows('view', $record))
                    ->modalHeading(fn (Allotment $record): string => "Entries for allotment {$record->sub_allotment_number}")
                    ->modalWidth('5xl')
                    ->schema(fn (Allotment $record): array => [
                        Section::make('Expense summary')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('sub_allotment_number')->label('Sub-allotment'),
                                TextEntry::make('year'),
                                TextEntry::make('authorization_type')->label('Authorization'),
                                TextEntry::make('total_amount')
                                    ->label('Total Allotment Amount')
                                    ->state(fn (Allotment $record): float => $record->totalAmount())
                                    ->money('PHP'),
                                TextEntry::make('remaining_amount')
                                    ->label('Remaining Allotment Amount')
                                    ->state(fn (Allotment $record): float => $record->remainingAmount())
                                    ->money('PHP'),
                                TextEntry::make('utilized_amount')
                                    ->label('Utilized Amount')
                                    ->state(fn (Allotment $record): float => $record->utilizedAmount())
                                    ->money('PHP'),
                                TextEntry::make('utilized_percentage')
                                    ->label('Utilization Rate')
                                    ->state(fn (Allotment $record): float => $record->utilizedAmount() > 0 ? round(($record->utilizedAmount() / $record->totalAmount()) * 100, 1) : 0)
                                    ->suffix('%'),
                            ]),
                        SchemaView::make('filament.app.resources.performance-indicators.relation-managers.allotment-entries-table')
                            ->viewData(['allotment' => $record])
                            ->columnSpanFull(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
