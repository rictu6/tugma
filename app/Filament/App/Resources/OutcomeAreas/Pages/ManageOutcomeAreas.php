<?php

namespace App\Filament\App\Resources\OutcomeAreas\Pages;

use App\Filament\App\Resources\OutcomeAreas\OutcomeAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOutcomeAreas extends ManageRecords
{
    protected static string $resource = OutcomeAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
