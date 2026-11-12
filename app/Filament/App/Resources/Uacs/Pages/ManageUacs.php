<?php

namespace App\Filament\App\Resources\Uacs\Pages;

use App\Filament\App\Resources\Uacs\UacsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUacs extends ManageRecords
{
    protected static string $resource = UacsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
