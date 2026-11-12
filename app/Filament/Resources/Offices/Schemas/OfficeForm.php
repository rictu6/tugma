<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('division')
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->required()
                    ->default(true),
            ]);
    }
}
