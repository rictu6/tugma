<?php

namespace App\Filament\App\Resources\PerformanceIndicators\Pages;

use App\Filament\App\Resources\PerformanceIndicators\PerformanceIndicatorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceIndicator extends EditRecord
{
    protected static string $resource = PerformanceIndicatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
