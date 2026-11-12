<?php

namespace App\Filament\App\Widgets;

use App\Models\Accomplishment;
use App\Models\FiscalYear;
use App\Models\Target;
use App\Services\QuarterlyPerformanceReport;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

class QuarterlyPerformance extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.app.widgets.quarterly-performance';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Gate::allows('viewAny', FiscalYear::class)
            && Gate::allows('viewAny', Target::class)
            && Gate::allows('viewAny', Accomplishment::class);
    }

    protected function getViewData(): array
    {
        return app(QuarterlyPerformanceReport::class)->tables(
            $this->pageFilters['fiscal_year_id'] ?? null,
            $this->pageFilters['opb_type'] ?? null,
            $this->pageFilters['office_id'] ?? null,
        );
    }
}
