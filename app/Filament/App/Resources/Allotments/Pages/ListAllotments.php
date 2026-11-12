<?php

namespace App\Filament\App\Resources\Allotments\Pages;

use App\Filament\App\Resources\Allotments\AllotmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllotments extends ListRecords
{
    protected static string $resource = AllotmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Allotment'),
        ];
    }
}
