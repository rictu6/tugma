<?php

namespace App\Filament\Resources\Uacs\Pages;

use App\Filament\Resources\Uacs\UacsResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUacs extends CreateRecord
{
    protected static string $resource = UacsResource::class;

    protected ?bool $hasDatabaseTransactions = true;
}
