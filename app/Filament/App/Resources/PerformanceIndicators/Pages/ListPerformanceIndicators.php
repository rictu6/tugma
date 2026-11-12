<?php

namespace App\Filament\App\Resources\PerformanceIndicators\Pages;

use App\Filament\App\Resources\PerformanceIndicators\PerformanceIndicatorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceIndicators extends ListRecords
{
    protected static string $resource = PerformanceIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
