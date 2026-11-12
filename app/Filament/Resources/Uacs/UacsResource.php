<?php

namespace App\Filament\Resources\Uacs;

use App\Filament\Resources\Uacs\Pages\CreateUacs;
use App\Filament\Resources\Uacs\Pages\EditUacs;
use App\Filament\Resources\Uacs\Pages\ListUacs;
use App\Filament\Resources\Uacs\Schemas\UacsForm;
use App\Filament\Resources\Uacs\Tables\UacsTable;
use App\Models\Uacs;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class UacsResource extends Resource
{
    protected static ?string $model = Uacs::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'System Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'UACS classification';

    protected static ?string $pluralModelLabel = 'UACS classifications';

    protected static ?string $navigationLabel = 'UACS';

    protected static ?string $slug = 'uacs';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return UacsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UacsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUacs::route('/'),
            'create' => CreateUacs::route('/create'),
            'edit' => EditUacs::route('/{record}/edit'),
        ];
    }
}
