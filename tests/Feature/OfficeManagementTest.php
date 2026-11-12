<?php

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\OfficeDeletePermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->administrator = User::factory()->create();
    $this->administrator->assignRole('RICTU');
    $this->actingAs($this->administrator);
});

function officeWithPerformanceIndicator(): Office
{
    $office = Office::create(['code' => 'LINKED', 'name' => 'Linked Office', 'division' => 'Division']);
    $fiscalYearId = DB::table('fiscal_years')->insertGetId([
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $targetId = DB::table('targets')->insertGetId(['fiscal_year_id' => $fiscalYearId]);
    $outcomeAreaId = DB::table('outcome_areas')->insertGetId([
        'name' => 'Outcome',
        'short_code' => 'OUT',
    ]);
    $programId = DB::table('programs')->insertGetId(['outcome_area_id' => $outcomeAreaId, 'name' => 'Program']);
    DB::table('performance_indicators')->insert([
        'target_id' => $targetId,
        'program_id' => $programId,
        'office_id' => $office->id,
        'code' => 'PI',
        'description' => 'Indicator',
    ]);

    return $office;
}

it('creates an active office and updates its status', function () {
    Livewire::test(CreateOffice::class)
        ->fillForm(['code' => 'OFF-01', 'name' => 'Office One', 'division' => 'Division One'])
        ->call('create')
        ->assertHasNoFormErrors();

    $office = Office::where('code', 'OFF-01')->firstOrFail();
    expect($office->is_active)->toBeTrue();

    Livewire::test(EditOffice::class, ['record' => $office->id])
        ->fillForm(['name' => 'Updated Office', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($office->fresh()->name)->toBe('Updated Office')
        ->and($office->fresh()->is_active)->toBeFalse();
});

it('validates required fields, length, and unique office codes', function () {
    Office::create(['code' => 'EXISTING', 'name' => 'Existing', 'division' => 'Division']);

    Livewire::test(CreateOffice::class)
        ->fillForm(['code' => null, 'name' => null, 'division' => null])
        ->call('create')
        ->assertHasFormErrors(['code' => 'required', 'name' => 'required', 'division' => 'required']);

    Livewire::test(CreateOffice::class)
        ->fillForm(['code' => 'EXISTING', 'name' => str_repeat('a', 256), 'division' => str_repeat('b', 256)])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique', 'name' => 'max', 'division' => 'max']);
});

it('rejects another office code when editing but accepts its own', function () {
    $office = Office::create(['code' => 'FIRST', 'name' => 'First', 'division' => 'Division']);
    Office::create(['code' => 'SECOND', 'name' => 'Second', 'division' => 'Division']);

    Livewire::test(EditOffice::class, ['record' => $office->id])
        ->fillForm(['code' => 'SECOND'])
        ->call('save')
        ->assertHasFormErrors(['code' => 'unique']);

    Livewire::test(EditOffice::class, ['record' => $office->id])
        ->fillForm(['code' => 'FIRST', 'name' => 'Changed'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('restricts office permissions to the intended roles', function () {
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $srmUser = User::factory()->create();
    $srmUser->assignRole('SRMU');
    $budgetUser = User::factory()->create();
    $budgetUser->assignRole('BUDGET');

    expect($this->administrator->can('create', Office::class))->toBeTrue()
        ->and($this->administrator->can('update', $office))->toBeTrue()
        ->and($this->administrator->can('delete', $office))->toBeTrue();

    foreach ([$srmUser, $budgetUser] as $user) {
        expect($user->can('view', $office))->toBeTrue()
            ->and($user->can('create', Office::class))->toBeFalse()
            ->and($user->can('update', $office))->toBeFalse()
            ->and($user->can('delete', $office))->toBeFalse();
    }
});

it('denies non-administrators direct access to office management', function () {
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $user = User::factory()->create();
    $user->assignRole('SRMU');

    $this->actingAs($user)->get('/admin/offices')->assertForbidden();
    $this->actingAs($user)->get('/admin/offices/create')->assertForbidden();
    $this->actingAs($user)->get('/admin/offices/'.$office->id.'/edit')->assertForbidden();
});

it('filters offices by active status', function () {
    $activeOffice = Office::create(['code' => 'ACTIVE', 'name' => 'Active', 'division' => 'Division', 'is_active' => true]);
    $inactiveOffice = Office::create(['code' => 'INACTIVE', 'name' => 'Inactive', 'division' => 'Division', 'is_active' => false]);

    Livewire::test(ListOffices::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$activeOffice])
        ->assertCanNotSeeTableRecords([$inactiveOffice])
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactiveOffice])
        ->assertCanNotSeeTableRecords([$activeOffice]);
});

it('toggles an office status from the table', function () {
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);

    Livewire::test(ListOffices::class)
        ->call('updateTableColumnState', 'is_active', (string) $office->id, false)
        ->assertSuccessful();

    expect($office->fresh()->is_active)->toBeFalse();

    Livewire::test(ListOffices::class)
        ->call('updateTableColumnState', 'is_active', (string) $office->id, true)
        ->assertSuccessful();

    expect($office->fresh()->is_active)->toBeTrue();
});

it('does not allow a user without update permission to toggle office status', function () {
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $this->administrator->roles()->firstOrFail()->revokePermissionTo('offices.update');

    Livewire::test(ListOffices::class)
        ->assertTableColumnExists('is_active', fn ($column): bool => $column->isDisabled(), $office)
        ->call('updateTableColumnState', 'is_active', (string) $office->id, false);

    expect($office->fresh()->is_active)->toBeTrue();
});

it('blocks deletion of referenced offices and permits unreferenced offices', function () {
    $linkedOffice = officeWithPerformanceIndicator();
    $emptyOffice = Office::create(['code' => 'EMPTY', 'name' => 'Empty', 'division' => 'Division']);

    expect($this->administrator->can('delete', $linkedOffice))->toBeFalse()
        ->and($this->administrator->can('delete', $emptyOffice))->toBeTrue();

    Livewire::test(EditOffice::class, ['record' => $linkedOffice->id])
        ->assertActionHidden('delete');

    Livewire::test(ListOffices::class)
        ->assertTableActionHidden('delete', $linkedOffice);

    Livewire::test(EditOffice::class, ['record' => $emptyOffice->id])
        ->callAction('delete');

    $this->assertDatabaseMissing('offices', ['id' => $emptyOffice->id]);
    $this->assertDatabaseHas('offices', ['id' => $linkedOffice->id]);
});

it('checks the policy for every office in a bulk deletion', function () {
    $linkedOffice = officeWithPerformanceIndicator();
    $emptyOffice = Office::create(['code' => 'EMPTY', 'name' => 'Empty', 'division' => 'Division']);

    Livewire::test(ListOffices::class)
        ->callTableBulkAction('delete', [$linkedOffice, $emptyOffice]);

    $this->assertDatabaseHas('offices', ['id' => $linkedOffice->id]);
    $this->assertDatabaseMissing('offices', ['id' => $emptyOffice->id]);
});

it('adds the office delete permission without replacing existing role permissions', function () {
    $role = $this->administrator->roles()->firstOrFail();
    $role->revokePermissionTo('offices.delete');
    $permissionsBefore = $role->permissions()->pluck('name')->sort()->values()->all();

    $this->seed(OfficeDeletePermissionSeeder::class);
    $this->seed(OfficeDeletePermissionSeeder::class);

    expect($role->fresh()->hasPermissionTo('offices.delete'))->toBeTrue()
        ->and($role->fresh()->permissions()->where('name', '!=', 'offices.delete')->pluck('name')->sort()->values()->all())
        ->toBe($permissionsBefore);
});
