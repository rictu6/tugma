<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SystemSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System Management';

    protected static ?string $navigationLabel = 'System Management';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.system-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('RICTU') ?? false;
    }

    public function mount(): void
    {
        $setting = SystemSetting::query()->find(1);
        $this->form->fill([
            'application_name' => $setting?->application_name ?? 'TUGMA',
            'support_email' => $setting?->support_email,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('application_name')->label('Application display name')->required()->maxLength(100),
            TextInput::make('support_email')->label('Support email')->email()->maxLength(255)->helperText('Contact information for administrators; not used for outgoing email.'),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();

        DB::transaction(function () use ($data): void {
            $setting = SystemSetting::query()->firstOrNew(['id' => 1]);
            if (! $setting->exists && $data['application_name'] === 'TUGMA' && blank($data['support_email'])) {
                return;
            }
            $setting->forceFill(['id' => 1]);
            $setting->fill($data);
            $setting->save();
        });

        Notification::make()->title('Settings saved')->success()->send();
    }
}
