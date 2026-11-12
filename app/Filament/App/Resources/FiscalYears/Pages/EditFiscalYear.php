<?php

namespace App\Filament\App\Resources\FiscalYears\Pages;

use App\Filament\App\Resources\FiscalYears\FiscalYearResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFiscalYear extends EditRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected bool $shouldActivate = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->shouldActivate = (bool) ($data['is_active'] ?? false);
        $data['is_active'] = false;

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->shouldActivate) {
            $this->record->activate();
        }
    }
}
