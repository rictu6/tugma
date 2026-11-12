<?php

namespace App\Filament\App\Resources\FiscalYears\Pages;

use App\Filament\App\Resources\FiscalYears\FiscalYearResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFiscalYear extends CreateRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected bool $shouldActivate = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->shouldActivate = (bool) ($data['is_active'] ?? false);
        $data['is_active'] = false;

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->shouldActivate) {
            $this->record->activate();
        }
    }
}
