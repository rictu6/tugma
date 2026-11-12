<?php

namespace App\Filament\Resources\Permissions\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        $role = Role::findByName('RICTU', 'web');
        $old = $role->permissions()->orderBy('name')->pluck('name')->all();
        $role->givePermissionTo($this->record);
        $new = $role->permissions()->orderBy('name')->pluck('name')->all();
        app(ActivityLogger::class)->record($role, 'permissions_changed', ['permissions' => $old], ['permissions' => $new]);
    }
}
