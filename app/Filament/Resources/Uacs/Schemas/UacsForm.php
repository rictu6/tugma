<?php

namespace App\Filament\Resources\Uacs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UacsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('UACS code')->required()->maxLength(255)->unique(),
            TextInput::make('description')->required()->maxLength(255),
        ]);
    }
}
