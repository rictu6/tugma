<?php

namespace App\Filament\App\Resources\AllotmentEntries\Pages;

use App\Filament\App\Resources\AllotmentEntries\AllotmentEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAllotmentEntries extends ManageRecords
{
    protected static string $resource = AllotmentEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Allotment Entry'),
        ];
    }
}
