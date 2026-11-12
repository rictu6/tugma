<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Services\ActivityLogger;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->regex('/^[A-Z][A-Z0-9_-]*$/')
                ->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('guard_name', 'web'))
                ->disabled(fn (?Role $record): bool => $record && in_array($record->name, ['RICTU', 'SRMU', 'BUDGET'], true))
                ->helperText('Use uppercase letters, numbers, underscores, or hyphens. Core role names cannot be changed.'),
            Select::make('permissions')
                ->multiple()
                ->disabled(fn (?Role $record): bool => $record?->name === 'RICTU')
                ->relationship('permissions', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web'))
                ->searchable()
                ->preload()
                ->rules([Rule::exists('permissions', 'id')->where('guard_name', 'web')])
                ->saveRelationshipsUsing(function (Role $record, ?array $state): void {
                    $old = $record->permissions()->orderBy('name')->pluck('name')->all();
                    $record->syncPermissions(Permission::query()->where('guard_name', 'web')->whereKey($state ?? [])->get());
                    $new = $record->permissions()->orderBy('name')->pluck('name')->all();
                    app(ActivityLogger::class)->record($record, 'permissions_changed', ['permissions' => $old], ['permissions' => $new]);
                })
                ->helperText('RICTU retains all permissions. Custom roles require a core role for panel access. Fiscal year updates remain role-based for RICTU and SRMU.'),
        ]);
    }
}
