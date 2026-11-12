<?php

namespace App\Filament\App\Resources\PerformanceIndicators\Pages;

use App\Filament\App\Resources\PerformanceIndicators\PerformanceIndicatorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceIndicator extends ViewRecord
{
    protected static string $resource = PerformanceIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
