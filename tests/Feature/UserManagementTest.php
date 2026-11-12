<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('RICTU');
    $this->actingAs($this->administrator);
});

it('creates a user with a hashed password and assigned roles', function () {
    $roles = Role::query()->whereIn('name', ['SRMU', 'BUDGET'])->pluck('id')->all();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'new@example.com',
            'roles' => $roles,
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect(Hash::check('a-strong-password', $user->password))->toBeTrue()
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['BUDGET', 'SRMU']);
});

it('requires valid unique emails, names, roles, and confirmed passwords', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => null, 'email' => null, 'roles' => [], 'password' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'roles' => 'required', 'password' => 'required']);

    $role = Role::findByName('SRMU')->id;
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => str_repeat('a', 256),
            'email' => $this->administrator->email,
            'roles' => [$role],
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'max', 'email' => 'unique', 'password']);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'invalid',
            'roles' => [$role],
            'password' => 'a-strong-password',
            'password_confirmation' => 'mismatched-password',
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'email', 'password' => 'confirmed']);
});

it('rejects invalid and wrong-guard role assignments', function () {
    $wrongGuard = Role::create(['name' => 'wrong-guard', 'guard_name' => 'api']);

    foreach ([[$wrongGuard->id], [999999]] as $roles) {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'new@example.com',
                'roles' => $roles,
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
            ])
            ->call('create')
            ->assertHasFormErrors(['roles']);
    }

    $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
});

it('rejects passwords that exceed the bcrypt byte limit', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New User',
            'email' => 'new@example.com',
            'roles' => [Role::findByName('SRMU')->id],
            'password' => str_repeat('é', 37),
            'password_confirmation' => str_repeat('é', 37),
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);

    $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
});

it('allows an unchanged email but rejects another user email', function () {
    $user = User::factory()->create();
    $user->assignRole('SRMU');

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['email' => $this->administrator->email])
        ->call('save')
        ->assertHasFormErrors(['email' => 'unique']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['email' => $user->email])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('updates details and roles without changing a blank password', function () {
    $user = User::factory()->create();
    $user->assignRole('SRMU');
    $originalPassword = $user->password;
    $role = Role::findByName('BUDGET')->id;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Changed', 'roles' => [$role], 'password' => null, 'password_confirmation' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('Changed')
        ->and($user->fresh()->password)->toBe($originalPassword)
        ->and($user->fresh()->getRoleNames()->all())->toBe(['BUDGET']);
});

it('updates passwords and validates confirmation on edit', function () {
    $user = User::factory()->create();
    $user->assignRole('SRMU');

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['password' => 'new-secure-password', 'password_confirmation' => 'mismatch'])
        ->call('save')
        ->assertHasFormErrors(['password' => 'confirmed']);

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

it('does not let an administrator remove their own role or delete themselves', function () {
    $role = Role::findByName('SRMU')->id;
    Livewire::test(EditUser::class, ['record' => $this->administrator->id])
        ->fillForm(['name' => 'Updated Administrator', 'roles' => [$role]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionHidden('delete');

    expect($this->administrator->fresh()->hasRole('RICTU'))->toBeTrue()
        ->and($this->administrator->fresh()->name)->toBe('Updated Administrator');
});

it('allows deleting other users but prevents self deletion in bulk', function () {
    $user = User::factory()->create();
    $user->assignRole('SRMU');

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('delete', [$this->administrator, $user]);

    $this->assertDatabaseHas('users', ['id' => $this->administrator->id]);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});

it('restricts user management to RICTU', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    expect($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('create', User::class))->toBeFalse()
        ->and($user->can('update', $this->administrator))->toBeFalse()
        ->and($user->can('delete', $this->administrator))->toBeFalse();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    $this->actingAs($user)->get('/admin/users/'.$this->administrator->id.'/edit')->assertForbidden();
})->with(['SRMU', 'BUDGET']);
