<?php

namespace App\Filament\App\Resources\PerformanceIndicators;

use App\Filament\App\Resources\PerformanceIndicators\Pages\CreatePerformanceIndicator;
use App\Filament\App\Resources\PerformanceIndicators\Pages\EditPerformanceIndicator;
use App\Filament\App\Resources\PerformanceIndicators\Pages\ListPerformanceIndicators;
use App\Filament\App\Resources\PerformanceIndicators\Pages\ViewPerformanceIndicator;
use App\Filament\App\Resources\PerformanceIndicators\RelationManagers\AllotmentsRelationManager;
use App\Filament\App\Resources\PerformanceIndicators\RelationManagers\AccomplishmentsRelationManager;
use App\Models\AllotmentEntry;
use App\Models\Expense;
use App\Models\Office;
use App\Models\PerformanceIndicator;
use App\Models\Program;
use App\Models\Target;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Icons\Heroicon;
use Filament\Support\View\Components\BadgeComponent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PerformanceIndicatorResource extends Resource
{
    protected static ?string $model = PerformanceIndicator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookmark;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['program.outcomeArea', 'accomplishments'])
            ->addSelect([
                'utilized_amount' => AllotmentEntry::query()
                    ->selectRaw('COALESCE(SUM(allotment_entries.amount), 0)')
                    ->join('expenses', 'expenses.id', '=', 'allotment_entries.expense_id')
                    ->join('allotments', 'allotments.id', '=', 'expenses.allotment_id')
                    ->whereColumn('allotments.performance_indicator_id', 'performance_indicators.id'),
                'allotment_total' => Expense::query()
                    ->selectRaw('COALESCE(SUM(expenses.amount), 0)')
                    ->join('allotments', 'allotments.id', '=', 'expenses.allotment_id')
                    ->whereColumn('allotments.performance_indicator_id', 'performance_indicators.id'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('target_id')
                    ->relationship('target', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Target $record): string => $record->fiscalYear->year . ' — ' . ucfirst($record->opb_type))
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('program_id')
                    ->relationship('program', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->with('outcomeArea'))
                    ->getOptionLabelFromRecordUsing(fn (Program $record): string => $record->name . ' — ' . $record->outcomeArea->name . ($record->is_active && $record->outcomeArea->is_active ? '' : ' (Inactive)'))
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('office_id')
                    ->relationship('office', 'name')
                    ->getOptionLabelFromRecordUsing(fn(Office $record): string => $record->name . ' — ' . $record->code . ($record->is_active ? '' : ' (Inactive)'))
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('code')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->groups([
                Group::make('program_id')
                    ->label('Program')
                    ->getTitleFromRecordUsing(fn (PerformanceIndicator $record): string => $record->program->outcomeArea->name . ' — ' . $record->program->name)
                    ->collapsible(),
                Group::make('program.outcome_area_id')
                    ->label('Outcome area')
                    ->getTitleFromRecordUsing(fn (PerformanceIndicator $record): string => $record->program->outcomeArea->name)
                    ->collapsible(),
            ])
            ->defaultGroup('program_id')
            ->columns([
                TextColumn::make('target.fiscalYear.year')
                    ->label('Fiscal year')
                    ->description(fn (PerformanceIndicator $record): string => ucfirst($record->target->opb_type))
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Indicator Code')
                    ->description(fn(PerformanceIndicator $record): ?string => $record->program?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Target')
                    ->description(fn(PerformanceIndicator $record): ?string => $record->office?->code)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('accomplishments_count')
                    ->label('Targets')
                    ->state(function (PerformanceIndicator $record): string|array {
                        $units = $record->accomplishments->pluck('unit')->filter(fn (?string $unit): bool => in_array($unit, ['number', 'percentage'], true))->unique();
                        $units = $units->isEmpty() ? collect(['number']) : $units->sort()->values();
                        $values = $units->map(function (string $unit) use ($record): string {
                            $totals = $record->accomplishmentTotals($unit);
                            $suffix = $unit === 'percentage' ? '%' : '';

                            return ($totals['actual'] === null ? '-' : number_format($totals['actual'], 2) . $suffix)
                                . ' / ' . ($totals['target'] === null ? '-' : number_format($totals['target'], 2) . $suffix);
                        });

                        return $values->count() === 1 ? $values->first() : $values->all();
                    })
                    ->listWithLineBreaks()
                    ->color(function (PerformanceIndicator $record, string $state): ?string {
                        if ($state === '- / -') {
                            return null;
                        }

                        $totals = $record->accomplishmentTotals(str_contains($state, '%') ? 'percentage' : 'number');

                        if ($totals['actual'] === null || $totals['target'] === null) {
                            return null;
                        }

                        return $totals['actual'] < $totals['target'] ? 'danger' : 'success';
                    }),
                TextColumn::make('utilized_amount')
                    ->label('Amount Utilized')
                    ->money('PHP')
                    ->description(function (PerformanceIndicator $record): HtmlString {
                        $percentage = (float) $record->allotment_total > 0
                            ? min(100, round(((float) $record->utilized_amount / (float) $record->allotment_total) * 100, 2))
                            : 0;

                        $color = match (true) {
                            $percentage >= 85 => 'success',
                            $percentage >= 50 => 'warning',
                            $percentage >= 1 => 'danger',
                            default => 'gray',
                        };

                        return new HtmlString(sprintf(
                            '<span class="fi-badge fi-size-sm %s">%s</span>',
                            implode(' ', FilamentColor::getComponentClasses(BadgeComponent::class, $color)),
                            number_format($percentage, 2) . '%',
                        ));
                    })
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ViewAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerformanceIndicators::route('/'),
            'create' => CreatePerformanceIndicator::route('/create'),
            'view' => ViewPerformanceIndicator::route('/{record}'),
            'edit' => EditPerformanceIndicator::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            AccomplishmentsRelationManager::class,
            AllotmentsRelationManager::class,
        ];
    }
}
