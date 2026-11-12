<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->regex('/^[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*$/')
                ->unique(modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('guard_name', 'web'))
                ->helperText('Use module.action, for example reports.view. Existing names cannot be changed or deleted. New permissions also need application checks to take effect.'),
        ]);
    }
}
