<?php

namespace App\Filament\App\Resources\Allotments\Pages;

use App\Filament\App\Resources\Allotments\AllotmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAllotment extends ViewRecord
{
    protected static string $resource = AllotmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
