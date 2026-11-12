<?php

use App\Filament\Resources\Uacs\Pages\CreateUacs;
use App\Filament\Resources\Uacs\Pages\EditUacs;
use App\Filament\Resources\Uacs\Pages\ListUacs;
use App\Models\ActivityLog;
use App\Models\Uacs;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
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

it('creates and updates classifications with an audit trail', function () {
    Livewire::test(CreateUacs::class)
        ->fillForm(['code' => '50101010', 'description' => 'Salaries'])
        ->call('create')
        ->assertHasNoFormErrors();

    $classification = Uacs::query()->where('code', '50101010')->firstOrFail();
    expect(ActivityLog::query()->where('module', 'Uacs')->where('subject_id', $classification->id)->where('action', 'created')->exists())->toBeTrue();

    Livewire::test(EditUacs::class, ['record' => $classification->id])
        ->fillForm(['description' => 'Updated salaries'])
        ->call('save')
        ->assertHasNoFormErrors();

    $log = ActivityLog::query()->where('module', 'Uacs')->where('subject_id', $classification->id)->where('action', 'updated')->firstOrFail();
    expect($classification->fresh()->description)->toBe('Updated salaries')
        ->and($log->old_values['description'])->toBe('Salaries')
        ->and($log->new_values['description'])->toBe('Updated salaries');
});

it('validates required fields, lengths, and unique codes', function () {
    $classification = Uacs::create(['code' => 'FIRST', 'description' => 'First']);
    Uacs::create(['code' => 'SECOND', 'description' => 'Second']);

    Livewire::test(CreateUacs::class)->fillForm(['code' => null, 'description' => null])
        ->call('create')->assertHasFormErrors(['code' => 'required', 'description' => 'required']);
    Livewire::test(CreateUacs::class)->fillForm(['code' => 'FIRST', 'description' => str_repeat('a', 256)])
        ->call('create')->assertHasFormErrors(['code' => 'unique', 'description' => 'max']);
    Livewire::test(CreateUacs::class)->fillForm(['code' => str_repeat('a', 256), 'description' => 'Valid'])
        ->call('create')->assertHasFormErrors(['code' => 'max']);
    Livewire::test(EditUacs::class, ['record' => $classification->id])->fillForm(['code' => 'SECOND'])
        ->call('save')->assertHasFormErrors(['code' => 'unique']);
    Livewire::test(EditUacs::class, ['record' => $classification->id])->fillForm(['code' => 'FIRST', 'description' => 'Changed'])
        ->call('save')->assertHasNoFormErrors();
});

it('searches classifications by code and description', function () {
    $first = Uacs::create(['code' => '50101010', 'description' => 'Salaries']);
    $second = Uacs::create(['code' => '50201010', 'description' => 'Travel']);

    Livewire::test(ListUacs::class)->searchTable('50101010')
        ->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second])
        ->searchTable('Travel')->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first]);
});

it('uses existing permissions while restricting the admin panel', function (string $roleName, bool $canManage) {
    $classification = Uacs::create(['code' => 'TEST', 'description' => 'Test']);
    $user = User::factory()->create();
    $user->assignRole($roleName);

    expect($this->administrator->can('viewAny', Uacs::class))->toBeTrue()
        ->and($this->administrator->can('create', Uacs::class))->toBeTrue()
        ->and($this->administrator->can('update', $classification))->toBeTrue()
        ->and($user->can('viewAny', Uacs::class))->toBeTrue()
        ->and($user->can('create', Uacs::class))->toBe($canManage)
        ->and($user->can('update', $classification))->toBe($canManage);

    $this->actingAs($user)->get('/admin/uacs')->assertForbidden();
    $this->actingAs($user)->get('/admin/uacs/create')->assertForbidden();
    $this->actingAs($user)->get('/admin/uacs/'.$classification->id.'/edit')->assertForbidden();
})->with([['SRMU', false], ['BUDGET', true]]);

it('enforces permissions even for administrators and never exposes deletion', function () {
    $classification = Uacs::create(['code' => 'TEST', 'description' => 'Test']);
    $this->get('/admin/uacs')->assertOk();
    $this->get('/admin/uacs/create')->assertOk();
    $this->get('/admin/uacs/'.$classification->id.'/edit')->assertOk();
    expect($this->administrator->can('delete', $classification))->toBeFalse()
        ->and($this->administrator->can('deleteAny', Uacs::class))->toBeFalse();
    Livewire::test(EditUacs::class, ['record' => $classification->id])->assertActionDoesNotExist('delete');
    $role = $this->administrator->roles()->firstOrFail();
    $role->revokePermissionTo('uacs.create');
    $role->revokePermissionTo('uacs.update');
    $this->administrator->unsetRelation('roles');
    $this->get('/admin/uacs/create')->assertForbidden();
    $this->get('/admin/uacs/'.$classification->id.'/edit')->assertForbidden();
});

it('keeps referenced classifications protected by the database foreign key', function () {
    $classification = Uacs::create(['code' => 'LINKED', 'description' => 'Linked']);
    $officeId = DB::table('offices')->insertGetId(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $yearId = DB::table('fiscal_years')->insertGetId(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $targetId = DB::table('targets')->insertGetId(['fiscal_year_id' => $yearId]);
    $outcomeId = DB::table('outcome_areas')->insertGetId(['name' => 'Outcome', 'short_code' => 'OUT']);
    $programId = DB::table('programs')->insertGetId(['outcome_area_id' => $outcomeId, 'name' => 'Program']);
    $indicatorId = DB::table('performance_indicators')->insertGetId(['target_id' => $targetId, 'program_id' => $programId, 'office_id' => $officeId, 'code' => 'PI', 'description' => 'Indicator']);
    $allotmentId = DB::table('allotments')->insertGetId(['performance_indicator_id' => $indicatorId, 'sub_allotment_number' => 'A', 'year' => 2026, 'authorization_type' => 'Test']);
    $expenseId = DB::table('expenses')->insertGetId(['allotment_id' => $allotmentId, 'uacs_id' => $classification->id, 'amount' => 100]);
    DB::table('allotment_entries')->insert(['expense_id' => $expenseId, 'entry_date' => '2026-01-01', 'amount' => 100]);

    expect($classification->expenses()->count())->toBe(1)
        ->and($this->administrator->can('delete', $classification))->toBeFalse();
    expect(fn () => DB::table('uacs')->where('id', $classification->id)->delete())->toThrow(QueryException::class);
    $this->assertDatabaseHas('uacs', ['id' => $classification->id]);
});
