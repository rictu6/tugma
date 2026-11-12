<?php

namespace App\Filament\Resources\Uacs\Pages;

use App\Filament\Resources\Uacs\UacsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUacs extends ListRecords
{
    protected static string $resource = UacsResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
