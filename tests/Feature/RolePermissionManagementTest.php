<?php

use App\Filament\Resources\Permissions\Pages\CreatePermission;
use App\Filament\Resources\Permissions\Pages\ListPermissions;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('RICTU');
    $this->actingAs($this->administrator);
});

it('creates and updates a custom role with permissions', function () {
    $view = Permission::findByName('targets.view');
    $create = Permission::findByName('targets.create');

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'REPORT_VIEWER', 'permissions' => [$view->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::findByName('REPORT_VIEWER');
    expect($role->guard_name)->toBe('web')
        ->and($role->hasPermissionTo($view))->toBeTrue();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['name' => 'REPORT_EDITOR', 'permissions' => [$create->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()->name)->toBe('REPORT_EDITOR')
        ->and($role->fresh()->hasPermissionTo($create))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo($view))->toBeFalse();
});

it('updates cached permissions when role permissions change', function () {
    $user = User::factory()->create();
    $role = Role::findByName('SRMU');
    $user->assignRole($role);
    $permission = Permission::findByName('targets.create');
    expect($user->can('targets.create'))->toBeTrue();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['permissions' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->can('targets.create'))->toBeFalse();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['permissions' => [$permission->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->can('targets.create'))->toBeTrue();
});

it('validates role names and permission guards', function () {
    $wrongGuard = Permission::create(['name' => 'other.view', 'guard_name' => 'api']);

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'SRMU', 'permissions' => []])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    Livewire::test(CreateRole::class)
        ->fillForm(['name' => 'invalid name', 'permissions' => []])
        ->call('create')
        ->assertHasFormErrors(['name' => 'regex']);

    foreach ([[$wrongGuard->id], [999999]] as $permissions) {
        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'VALID_ROLE', 'permissions' => $permissions])
            ->call('create')
            ->assertHasFormErrors(['permissions']);
    }

    $this->assertDatabaseMissing('roles', ['name' => 'VALID_ROLE']);
});

it('protects core role names and RICTU permissions from tampering', function () {
    $role = Role::findByName('RICTU');
    $permissions = $role->permissions()->pluck('id')->sort()->values()->all();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->fillForm(['name' => 'CHANGED', 'permissions' => []])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionHidden('delete');

    expect($role->fresh()->name)->toBe('RICTU')
        ->and($role->fresh()->permissions()->pluck('id')->sort()->values()->all())->toBe($permissions);

    foreach (['SRMU', 'BUDGET'] as $name) {
        $coreRole = Role::findByName($name);
        Livewire::test(EditRole::class, ['record' => $coreRole->id])
            ->fillForm(['name' => 'CHANGED'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionHidden('delete');
        expect($coreRole->fresh()->name)->toBe($name);
    }
});

it('only deletes unassigned custom roles even in bulk', function () {
    $assigned = Role::create(['name' => 'ASSIGNED', 'guard_name' => 'web']);
    $empty = Role::create(['name' => 'EMPTY', 'guard_name' => 'web']);
    $core = Role::findByName('SRMU');
    $user = User::factory()->create();
    $user->assignRole($assigned);

    Livewire::test(ListRoles::class)
        ->callTableBulkAction('delete', [$assigned, $empty, $core]);

    $this->assertDatabaseHas('roles', ['id' => $assigned->id]);
    $this->assertDatabaseHas('roles', ['id' => $core->id]);
    $this->assertDatabaseMissing('roles', ['id' => $empty->id]);
});

it('creates permissions and grants them to RICTU without resetting role assignments', function () {
    $srmRole = Role::findByName('SRMU');
    $before = $srmRole->permissions()->pluck('id')->sort()->values()->all();

    Livewire::test(CreatePermission::class)
        ->fillForm(['name' => 'reports.view'])
        ->call('create')
        ->assertHasNoFormErrors();

    $permission = Permission::findByName('reports.view');
    expect($permission->guard_name)->toBe('web')
        ->and(Role::findByName('RICTU')->hasPermissionTo($permission))->toBeTrue()
        ->and($srmRole->fresh()->permissions()->pluck('id')->sort()->values()->all())->toBe($before);

    $this->seed(RolePermissionSeeder::class);
    expect(Role::findByName('RICTU')->hasPermissionTo($permission))->toBeTrue();
});

it('validates and protects permission names', function () {
    Livewire::test(CreatePermission::class)
        ->fillForm(['name' => 'targets.view'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);

    foreach (['invalid', 'Targets.view', 'view', 'reports.*'] as $name) {
        Livewire::test(CreatePermission::class)
            ->fillForm(['name' => $name])
            ->call('create')
            ->assertHasFormErrors(['name' => 'regex']);
    }

    $permission = Permission::findByName('targets.view');
    expect($this->administrator->can('update', $permission))->toBeFalse()
        ->and($this->administrator->can('delete', $permission))->toBeFalse();
});

it('limits the resources to web guard records', function () {
    $role = Role::create(['name' => 'API_ROLE', 'guard_name' => 'api']);
    $permission = Permission::create(['name' => 'api.view', 'guard_name' => 'api']);

    Livewire::test(ListRoles::class)->assertCanNotSeeTableRecords([$role]);
    Livewire::test(ListPermissions::class)->assertCanNotSeeTableRecords([$permission]);
    $this->get('/admin/roles/'.$role->id.'/edit')->assertNotFound();
});

it('blocks non-RICTU users from managing roles and permissions', function (string $roleName) {
    $user = User::factory()->create();
    $user->assignRole($roleName);
    $role = Role::findByName('SRMU');
    $permission = Permission::findByName('targets.view');

    expect($user->can('viewAny', Role::class))->toBeFalse()
        ->and($user->can('create', Role::class))->toBeFalse()
        ->and($user->can('update', $role))->toBeFalse()
        ->and($user->can('viewAny', Permission::class))->toBeFalse()
        ->and($user->can('create', Permission::class))->toBeFalse()
        ->and($user->can('delete', $permission))->toBeFalse();

    $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    $this->actingAs($user)->get('/admin/roles/create')->assertForbidden();
    $this->actingAs($user)->get('/admin/roles/'.$role->id.'/edit')->assertForbidden();
    $this->actingAs($user)->get('/admin/permissions')->assertForbidden();
    $this->actingAs($user)->get('/admin/permissions/create')->assertForbidden();
})->with(['SRMU', 'BUDGET']);
