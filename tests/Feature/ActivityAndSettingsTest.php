<?php

use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\ActivityLog;
use App\Models\Office;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->administrator = User::factory()->create(['name' => 'Administrator']);
    $this->administrator->assignRole('RICTU');
    $this->actingAs($this->administrator);
});

it('audits changes, redacts credentials, and preserves actor names', function () {
    $office = Office::create(['code' => 'ABC', 'name' => 'Before', 'division' => 'Division']);
    $office->update(['name' => 'After']);
    $log = ActivityLog::query()->where('module', 'Office')->where('action', 'updated')->firstOrFail();
    expect($log->old_values['name'])->toBe('Before')
        ->and($log->new_values['name'])->toBe('After')
        ->and($log->actor_name)->toBe('Administrator');

    $user = User::factory()->create(['password' => 'secret-password']);
    $user->update(['password' => 'new-secret-password']);
    $logs = ActivityLog::query()->where('module', 'User')->where('subject_id', $user->id)->get()->toJson();
    expect($logs)->not->toContain('secret-password')->not->toContain($user->password);

    $office->delete();
    expect(ActivityLog::query()->where('module', 'Office')->where('action', 'deleted')->exists())->toBeTrue();
    $this->actingAs($user);
    $this->administrator->delete();
    expect($log->fresh()->user_id)->toBeNull()->and($log->fresh()->actor_name)->toBe('Administrator');
});

it('rolls audit records back with the operation', function () {
    try {
        DB::transaction(function (): void {
            Office::create(['code' => 'ROLLBACK', 'name' => 'Rollback', 'division' => 'Test']);
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Rollback');
    }
    expect(ActivityLog::query()->where('module', 'Office')->exists())->toBeFalse();
});

it('audits role and permission changes made through management forms', function () {
    $user = User::factory()->create();
    $user->assignRole('SRMU');
    $budget = Role::findByName('BUDGET');
    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['roles' => [$budget->id]])->call('save')->assertHasNoFormErrors();
    $log = ActivityLog::query()->where('action', 'roles_changed')->where('subject_id', $user->id)->latest('id')->firstOrFail();
    expect($log->old_values['roles'])->toBe(['SRMU'])->and($log->new_values['roles'])->toBe(['BUDGET']);

    $role = Role::findByName('SRMU');
    $permission = Permission::findByName('targets.view');
    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['permissions' => [$permission->id]])->call('save')->assertHasNoFormErrors();
    $log = ActivityLog::query()->where('action', 'permissions_changed')->where('subject_id', $role->id)->latest('id')->firstOrFail();
    expect($log->new_values['permissions'])->toBe(['targets.view'])->and(count($log->old_values['permissions']))->toBeGreaterThan(1);

    Livewire::test(CreatePermission::class)->fillForm(['name' => 'reports.view'])->call('create')->assertHasNoFormErrors();
    $log = ActivityLog::query()->where('action', 'permissions_changed')->where('subject_id', Role::findByName('RICTU')->id)->latest('id')->firstOrFail();
    expect($log->new_values['permissions'])->toContain('reports.view');
});

it('validates, saves, applies, and audits system settings', function () {
    Livewire::test(SystemSettings::class)->call('save')->assertHasNoFormErrors();
    expect(SystemSetting::query()->count())->toBe(0);
    Livewire::test(SystemSettings::class)->fillForm(['application_name' => '', 'support_email' => 'invalid'])
        ->call('save')->assertHasFormErrors(['application_name', 'support_email']);
    Livewire::test(SystemSettings::class)->fillForm(['application_name' => 'New TUGMA', 'support_email' => 'help@example.com'])
        ->call('save')->assertHasNoFormErrors();
    expect(SystemSetting::applicationName())->toBe('New TUGMA')
        ->and(Filament::getPanel('admin')->getBrandName())->toBe('New TUGMA Admin Panel')
        ->and(Filament::getPanel('app')->getBrandName())->toBe('New TUGMA');
    $count = ActivityLog::query()->where('module', 'SystemSetting')->count();
    Livewire::test(SystemSettings::class)->call('save')->assertHasNoFormErrors();
    expect(ActivityLog::query()->where('module', 'SystemSetting')->count())->toBe($count);
    Livewire::test(SystemSettings::class)->fillForm(['application_name' => 'Other TUGMA'])->call('save')->assertHasNoFormErrors();
    $log = ActivityLog::query()->where('module', 'SystemSetting')->where('action', 'updated')->firstOrFail();
    expect($log->old_values['application_name'])->toBe('New TUGMA')->and($log->new_values['application_name'])->toBe('Other TUGMA');
});

it('shows activity records and their details to administrators', function () {
    $office = Office::create(['code' => 'VIEW', 'name' => 'View me', 'division' => 'Test']);
    $log = ActivityLog::query()->where('module', 'Office')->where('subject_id', $office->id)->firstOrFail();
    $this->get('/admin/activity-logs')->assertOk();
    $this->get('/admin/system-settings')->assertOk();
    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$log])
        ->assertActionVisible(TestAction::make('view')->table($log))
        ->mountAction(TestAction::make('view')->table($log))
        ->assertActionMounted(TestAction::make('view')->table($log))
        ->assertSchemaComponentStateSet('new_values_display', json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
});

it('restricts logs and settings to RICTU and keeps logs read only', function (string $roleName) {
    $log = ActivityLog::query()->firstOrFail();
    expect($this->administrator->can('view', $log))->toBeTrue()
        ->and($this->administrator->can('create', ActivityLog::class))->toBeFalse()
        ->and($this->administrator->can('update', $log))->toBeFalse()
        ->and($this->administrator->can('delete', $log))->toBeFalse();
    $user = User::factory()->create();
    $user->assignRole($roleName);
    expect($user->can('viewAny', ActivityLog::class))->toBeFalse();
    $this->actingAs($user)->get('/admin/activity-logs')->assertForbidden();
    $this->actingAs($user)->get('/admin/system-settings')->assertForbidden();
})->with(['SRMU', 'BUDGET']);
