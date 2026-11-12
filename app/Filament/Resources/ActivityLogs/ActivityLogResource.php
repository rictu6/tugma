<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\ActivityLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Time')->dateTime()->sortable(),
                TextColumn::make('actor_name')->label('User')->placeholder('System')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('module')->searchable(),
                TextColumn::make('subject_id')->label('Record ID')->searchable(),
            ])
            ->filters([
                SelectFilter::make('action')->options(fn (): array => ActivityLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('module')->options(fn (): array => ActivityLog::query()->distinct()->orderBy('module')->pluck('module', 'module')->all()),
            ])
            ->recordActions([
                ViewAction::make()->infolist([
                    TextEntry::make('subject_type')->label('Record type'),
                    TextEntry::make('old_values_display')->label('Previous values')->state(fn (ActivityLog $record): string => json_encode($record->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
                    TextEntry::make('new_values_display')->label('New values')->state(fn (ActivityLog $record): string => json_encode($record->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListActivityLogs::route('/')];
    }
}
