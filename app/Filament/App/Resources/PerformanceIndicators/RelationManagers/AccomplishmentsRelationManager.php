<?php

namespace App\Filament\App\Resources\PerformanceIndicators\RelationManagers;

use App\Models\Accomplishment;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AccomplishmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'accomplishments';

    protected static ?string $recordTitleAttribute = 'type';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && Gate::allows('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->options(Accomplishment::typeOptions())
                ->required(),
            Select::make('unit')
                ->options([
                    'percentage' => 'Percentage',
                    'number' => 'Number',
                ]),
            ...self::accomplishmentFields(),
        ]);
    }

    public function table(Table $table): Table
    {
        $performanceIndicator = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn(Builder $query): Builder => $query->with([
                'performanceIndicator.target.fiscalYear',
                'performanceIndicator.accomplishments',
            ]))
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn(?string $state): string => Accomplishment::typeOptions()[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('target_quarter')
                    ->label('Target quarter / semester')
                    ->state(fn(Accomplishment $record): int|string => $record->target_quarter ?? '-')
                    ->formatStateUsing(fn(?string $state): string => match ((int) $state) {
                        1 => '1st Quarter',
                        2 => '2nd Quarter',
                        3 => '3rd Quarter',
                        4 => '4th Quarter',
                        default => (string) $state,
                    })
                    ->description(fn(Accomplishment $record): ?string => match ($record->target_semi_annual) {
                        1 => '1st Semester',
                        2 => '2nd Semester',
                        default => null,
                    })
                    ->sortable(),
                TextColumn::make('unit')
                    ->label('Unit')
                    ->formatStateUsing(fn(?string $state): string => match ($state) {
                        'percentage' => 'Percentage',
                        'number' => 'Number',
                        default => (string) $state,
                    })
                    ->placeholder('-'),
                TextColumn::make('target')
                    ->state(fn(Accomplishment $record): ?string => blank($record->target) ? null : $record->target)
                    ->placeholder('-')
                    ->summarize(Summarizer::make('total')
                        ->label('Total')
                        ->using(function (QueryBuilder $query): ?float {
                            $numericTargets = $query->get()->filter(
                                fn(object $record): bool => $record->unit === 'number' && is_numeric($record->target),
                            );

                            return $numericTargets->isEmpty()
                                ? null
                                : round((float) $numericTargets->sum(fn(object $record): float => (float) $record->target), 2);
                        })
                        ->numeric(decimalPlaces: 2)),
                TextColumn::make('actual')
                    ->label('Actual')
                    ->state(fn(Accomplishment $record): float|string|null => $record->actualAsNumber() ?? $record->actual)
                    ->formatStateUsing(fn(float|string $state, Accomplishment $record): string => $record->unit === 'percentage' && ! str_ends_with(trim((string) $state), '%')
                        ? (string) $state . '%'
                        : (string) $state)
                    ->color(fn(Accomplishment $record): ?string => match ($record->actualTargetComparison()) {
                        -1 => 'danger',
                        0, 1 => 'success',
                        default => null,
                    })
                    ->placeholder('-')
                    ->summarize(Summarizer::make()
                        ->label('Total')
                        ->using(function (QueryBuilder $query): ?float {
                            $numericActuals = $query->get()->filter(
                                fn(object $record): bool => $record->unit === 'number' && is_numeric($record->actual),
                            );

                            return $numericActuals->isEmpty()
                                ? null
                                : round((float) $numericActuals->sum(fn(object $record): float => (float) $record->actual), 2);
                        })
                        ->numeric(decimalPlaces: 2)),
                TextColumn::make('cumulative_actual')
                    ->label('Cumulative actual')
                    ->state(fn(Accomplishment $record): ?float => $record->cumulativeActual())
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-')
                    ->visible(fn(?Accomplishment $record): bool => $record?->type === 'cumulative'),
                TextColumn::make('performance_status')
                    ->label('Date accomplished / status')
                    ->state(fn(Accomplishment $record): string => $record->performanceStatus())
                    ->description(fn(Accomplishment $record): string => $record->date_accomplished?->format('M j, Y') ?? '-', position: 'above')
                    ->badge()
                    ->color(fn(Accomplishment $record): string => $record->performanceStatusColor())
                    ->sortable(['date_accomplished']),
                TextColumn::make('reason')
                    ->state(fn(Accomplishment $record): ?string => blank($record->reason) ? null : $record->reason)
                    ->placeholder('-'),
                TextColumn::make('tag')
                    ->formatStateUsing(fn (?string $state): ?string => Accomplishment::tagOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'met', 'exceeded' => 'success',
                        'unmet' => 'danger',
                        'cont', 'moved', 'deferred' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->schema([
                        Select::make('type')
                            ->options(Accomplishment::typeOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                $rows = Accomplishment::defaultPeriodCountForType($state);

                                $periods = $get('periods') ?? [];

                                if (count($periods) < $rows) {
                                    $set('periods', array_pad($periods, $rows, []));
                                }
                            }),
                        Select::make('unit')
                            ->options([
                                'percentage' => 'Percentage',
                                'number' => 'Number',
                            ]),
                        Repeater::make('periods')
                            ->label('Accomplishment periods')
                            ->schema(self::accomplishmentFields())
                            ->defaultItems(0)
                            ->minItems(1)
                            ->addActionLabel('Add period')
                            ->reorderable(false),
                    ])
                    ->action(function (array $data) use ($performanceIndicator): void {
                        DB::transaction(function () use ($data, $performanceIndicator): void {
                            foreach ($data['periods'] as $period) {
                                $performanceIndicator->accomplishments()->create([
                                    ...$period,
                                    'type' => $data['type'],
                                    'unit' => $data['unit'] ?? null,
                                ]);
                            }
                        });
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    private static function accomplishmentFields(): array
    {
        return [
            Section::make('Target')
                ->schema([
                    Select::make('target_quarter')
                        ->label('Target quarter')
                        ->options([
                            1 => '1st Quarter',
                            2 => '2nd Quarter',
                            3 => '3rd Quarter',
                            4 => '4th Quarter',
                        ])
                        ->live()
                        ->afterStateUpdated(fn(Set $set, ?string $state) => $set(
                            'target_semi_annual',
                            match ((int) $state) {
                                1, 2 => 1,
                                3, 4 => 2,
                                default => null,
                            },
                        ))
                        ->rules(['integer', 'between:1,4'])
                        ->required(),
                    Select::make('target_semi_annual')
                        ->label('Target semestral')
                        ->options(fn(Get $get): array => match ((int) $get('target_quarter')) {
                            1, 2 => [1 => '1st Semester'],
                            3, 4 => [2 => '2nd Semester'],
                            default => [],
                        })
                        ->rules(fn(Get $get): array => [
                            Rule::in(match ((int) $get('target_quarter')) {
                                1, 2 => [1],
                                3, 4 => [2],
                                default => [],
                            }),
                        ])
                        ->required(),
                    TextInput::make('target')
                        ->required()
                        ->maxLength(255),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Section::make('Accomplishment')
                ->schema([
                    DatePicker::make('date_accomplished'),
                    TextInput::make('actual')
                        ->numeric()
                        ->maxLength(255),
                    Select::make('tag')
                        ->options(Accomplishment::tagOptions())
                        ->live()
                        ->rules([Rule::in(array_keys(Accomplishment::tagOptions()))]),
                    Textarea::make('actual_accomplishment_description')
                        ->columnSpanFull(),
                    Select::make('reason')
                        ->options([
                            'No funds downloaded' => 'No funds downloaded',
                            'No policy guidelines from CO' => 'No policy guidelines from CO',
                            'No approved OPB from CO' => 'No approved OPB from CO',
                            'Deferred by CO' => 'Deferred by CO',
                            'Others, see remarks' => 'Others, see remarks',
                        ])
                        ->visible(fn(Get $get): bool => $get('tag') === 'deferred')
                        ->required(fn(Get $get): bool => $get('tag') === 'deferred')
                        ->native(false),
                    Textarea::make('remarks')
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }
}
