<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\QuarterlyPerformance;
use App\Models\FiscalYear;
use App\Models\Office;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        if (! QuarterlyPerformance::canView()) {
            return $schema->components([]);
        }

        return $schema->components([
            Section::make('Filters')
                ->schema([
                    Select::make('fiscal_year_id')
                        ->label('Fiscal year')
                        ->options(fn (): array => FiscalYear::query()->orderByDesc('year')->pluck('year', 'id')->all())
                        ->default(fn (): ?int => FiscalYear::query()->orderByDesc('is_active')->orderByDesc('year')->value('id'))
                        ->placeholder('All fiscal years'),
                    Select::make('opb_type')
                        ->label('OPB type')
                        ->options(['current' => 'Current', 'continuing' => 'Continuing'])
                        ->placeholder('All OPB types'),
                    Select::make('office_id')
                        ->label('Office')
                        ->options(fn (): array => Office::query()->orderBy('name')->get()->mapWithKeys(
                            fn (Office $office): array => [$office->id => $office->name . ' — ' . $office->code . ($office->is_active ? '' : ' (Inactive)')],
                        )->all())
                        ->searchable()
                        ->placeholder('All offices'),
                ])
                ->columns(['default' => 1, 'md' => 3])
                ->columnSpanFull(),
        ]);
    }

    public function getWidgets(): array
    {
        return [QuarterlyPerformance::class];
    }
}
