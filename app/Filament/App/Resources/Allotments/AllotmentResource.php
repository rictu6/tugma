<?php

namespace App\Filament\App\Resources\Allotments;

use App\Filament\App\Resources\Allotments\Pages\CreateAllotment;
use App\Filament\App\Resources\Allotments\Pages\EditAllotment;
use App\Filament\App\Resources\Allotments\Pages\ListAllotments;
use App\Filament\App\Resources\Allotments\Pages\ViewAllotment;
use App\Filament\App\Resources\Allotments\RelationManagers\ExpensesRelationManager;
use App\Models\Allotment;
use App\Models\PerformanceIndicator;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Icons\Heroicon;
use Filament\Support\View\Components\BadgeComponent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;

class AllotmentResource extends Resource
{
    protected static ?string $model = Allotment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $recordTitleAttribute = 'sub_allotment_number';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Management';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum('expenses as total_amount', 'amount')
            ->withSum('allotmentEntries as utilized_amount', 'amount');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('performance_indicator_id')
                    ->relationship('performanceIndicator', 'code')
                    ->getOptionLabelFromRecordUsing(fn(PerformanceIndicator $record): string => $record->code . ' — ' . $record->program->name)
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('sub_allotment_number')
                    ->required()
                    ->maxLength(255)
                    ->unique(modifyRuleUsing: fn(Unique $rule, Get $get): Unique => $rule->where('performance_indicator_id', $get('performance_indicator_id'))),
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

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sub_allotment_number')
            ->columns([
                TextColumn::make('performanceIndicator.code')
                    ->label('Performance indicator')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sub_allotment_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('authorization_type')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('PHP')
                    ->default(0)
                    ->sortable(),
                TextColumn::make('utilized_amount')
                    ->label('Amount Utilized')
                    ->money('PHP')
                    ->default(0)
                    ->description(function (Allotment $record): HtmlString {
                        $percentage = (float) $record->total_amount > 0
                            ? min(100, round(((float) $record->utilized_amount / (float) $record->total_amount) * 100, 2))
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
                    ->sortable()

            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(fn(): array => Allotment::query()->distinct()->orderBy('year')->pluck('year', 'year')->all()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ViewAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllotments::route('/'),
            'create' => CreateAllotment::route('/create'),
            'view' => ViewAllotment::route('/{record}'),
            'edit' => EditAllotment::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            ExpensesRelationManager::class,
        ];
    }
}
