<?php

namespace App\Filament\App\Resources\Targets;

use App\Actions\DuplicateTarget;
use App\Filament\App\Resources\Targets\Pages\ManageTargets;
use App\Models\FiscalYear;
use App\Models\OutcomeArea;
use App\Models\Program;
use App\Models\Target;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TargetResource extends Resource
{
    protected static ?string $model = Target::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'opb_type';

    protected static string|\UnitEnum|null $navigationGroup = 'Performance Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Targets';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('fiscal_year_id')
                    ->relationship('fiscalYear', 'year')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('opb_type')
                    ->options([
                        'current' => 'Current',
                        'continuing' => 'Continuing',
                    ])
                    ->required()
                    ->default('current'),
                TextInput::make('status')
                    ->required()
                    ->maxLength(255)
                    ->default('draft'),
                Select::make('outcomeAreas')
                    ->label('Outcome areas')
                    ->multiple()
                    ->relationship('outcomeAreas', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (): bool => Gate::allows('viewAny', OutcomeArea::class) && Gate::allows('viewAny', Program::class)),
                Select::make('programs')
                    ->label('Programs')
                    ->multiple()
                    ->relationship('programs', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => Gate::allows('viewAny', OutcomeArea::class) && Gate::allows('viewAny', Program::class))
                    ->rules([
                        fn (Get $get, ?Target $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $programIds = is_array($value) ? $value : [];
                            $outcomeAreaIds = $get('outcomeAreas');
                            $outcomeAreaIds = is_array($outcomeAreaIds) ? $outcomeAreaIds : [];

                            if (Program::query()->whereIn('id', $programIds)->whereNotIn('outcome_area_id', $outcomeAreaIds)->exists()) {
                                $fail('Select the outcome area for each selected program.');
                            }

                            if ($record && $record->performanceIndicators()->whereNotIn('program_id', $programIds)->exists()) {
                                $fail('Programs used by performance indicators must remain selected.');
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('opb_type')
            ->groups([
                Group::make('fiscalYear.year')->label('Fiscal year')->collapsible(),
                Group::make('opb_type')
                    ->label('OPB type')
                    ->getTitleFromRecordUsing(fn (Target $record): string => ucfirst($record->opb_type))
                    ->collapsible(),
            ])
            ->defaultGroup('fiscalYear.year')
            ->columns([
                TextColumn::make('fiscalYear.year')
                    ->label('Fiscal year')
                    ->sortable(),
                TextColumn::make('opb_type')
                    ->label('OPB type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('status')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('opb_type')
                    ->label('OPB type')
                    ->options(['current' => 'Current', 'continuing' => 'Continuing']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->visible(fn (Target $record): bool => Gate::allows('view', $record)
                        && Gate::allows('create', Target::class)
                        && Gate::allows('viewAny', OutcomeArea::class)
                        && Gate::allows('viewAny', Program::class))
                    ->schema([
                        Select::make('fiscal_year_id')
                            ->label('Destination fiscal year')
                            ->options(fn (): array => FiscalYear::query()->orderBy('year')->pluck('year', 'id')->all())
                            ->required()
                            ->rules([Rule::exists('fiscal_years', 'id')]),
                        Select::make('opb_type')
                            ->label('Destination OPB type')
                            ->options(['current' => 'Current', 'continuing' => 'Continuing'])
                            ->default(fn (Target $record): string => $record->opb_type)
                            ->required()
                            ->rules([Rule::in(['current', 'continuing'])]),
                    ])
                    ->action(function (Target $record, array $data): void {
                        Gate::authorize('view', $record);
                        Gate::authorize('create', Target::class);
                        Gate::authorize('viewAny', OutcomeArea::class);
                        Gate::authorize('viewAny', Program::class);

                        app(DuplicateTarget::class)($record, (int) $data['fiscal_year_id'], $data['opb_type']);
                    }),
                DeleteAction::make(),
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
            'index' => ManageTargets::route('/'),
        ];
    }
}
