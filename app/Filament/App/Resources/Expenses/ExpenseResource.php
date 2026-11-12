<?php

namespace App\Filament\App\Resources\Expenses;

use App\Filament\App\Resources\Expenses\Pages\ViewExpense;
use App\Filament\App\Resources\Expenses\RelationManagers\AllotmentEntriesRelationManager;
use App\Models\Expense;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Management';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['allotment.performanceIndicator', 'uacs'])
            ->withSum('allotmentEntries as utilized_amount', 'amount');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('allotment.sub_allotment_number')->label('Sub-allotment'),
                TextEntry::make('allotment.performanceIndicator.code')->label('Performance indicator'),
                TextEntry::make('uacs.code')->label('UACS code'),
                TextEntry::make('uacs.description')->label('Expense type'),
                TextEntry::make('amount')->money('PHP')->label('Expense amount'),
                TextEntry::make('utilized_amount')->money('PHP')->label('Amount utilized'),
                TextEntry::make('remaining_amount')
                    ->state(fn (Expense $record): float => $record->remainingAmount())
                    ->money('PHP'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AllotmentEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'view' => ViewExpense::route('/{record}'),
        ];
    }
}
