<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Services\ActivityLogger;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(),
            Select::make('roles')
                ->multiple()
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                ->relationship('roles', 'name', fn (Builder $query): Builder => $query->where('guard_name', 'web'))
                ->saveRelationshipsUsing(function (User $record, ?array $state): void {
                    $old = $record->roles()->orderBy('name')->pluck('name')->all();
                    $record->syncRoles(Role::query()->where('guard_name', 'web')->whereKey($state ?? [])->get());
                    $new = $record->roles()->orderBy('name')->pluck('name')->all();
                    app(ActivityLogger::class)->record($record, 'roles_changed', ['roles' => $old], ['roles' => $new]);
                })
                ->preload()
                ->helperText('A user needs RICTU, SRMU, or BUDGET to access a panel; custom roles provide additional permissions.')
                ->required()
                ->rules([Rule::exists('roles', 'id')->where('guard_name', 'web')]),
            TextInput::make('password')
                ->password()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->confirmed()
                ->rules([
                    Password::min(12),
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_string($value) && strlen($value) > 72) {
                            $fail('The password must not exceed 72 bytes.');
                        }
                    },
                ]),
            TextInput::make('password_confirmation')
                ->password()
                ->required(fn (Get $get): bool => filled($get('password')))
                ->dehydrated(false),
        ]);
    }
}
