<?php

use App\Filament\Resources\FiscalYears\Pages\CreateFiscalYear;
use App\Filament\Resources\FiscalYears\Pages\EditFiscalYear;
use App\Filament\Resources\FiscalYears\Pages\ListFiscalYears;
use App\Models\FiscalYear;
use App\Models\User;
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

function fiscalYearData(array $overrides = []): array
{
    return array_merge([
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_active' => false,
    ], $overrides);
}

function createFiscalYearTarget(FiscalYear $fiscalYear): void
{
    DB::table('targets')->insert(['fiscal_year_id' => $fiscalYear->id]);
}

it('creates fiscal years and validates required, unique, integer, and date fields', function () {
    $existingYear = FiscalYear::create(fiscalYearData());

    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData([
            'year' => null,
            'start_date' => null,
            'end_date' => null,
        ]))
        ->call('create')
        ->assertHasFormErrors([
            'year' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
        ]);

    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData(['year' => $existingYear->year]))
        ->call('create')
        ->assertHasFormErrors(['year' => 'unique']);

    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData(['year' => 2026.5]))
        ->call('create')
        ->assertHasFormErrors(['year' => 'integer']);

    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData([
            'year' => 2027,
            'start_date' => '2027-12-31',
            'end_date' => '2027-01-01',
        ]))
        ->call('create')
        ->assertHasFormErrors(['end_date' => 'after_or_equal']);
});

it('creates and updates a fiscal year while allowing its existing year', function () {
    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData(['year' => 2027]))
        ->call('create')
        ->assertHasNoFormErrors();

    $fiscalYear = FiscalYear::where('year', 2027)->firstOrFail();

    Livewire::test(EditFiscalYear::class, ['record' => $fiscalYear->id])
        ->fillForm(fiscalYearData([
            'year' => 2027,
            'start_date' => '2027-04-01',
            'end_date' => '2028-03-31',
        ]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fiscalYear->fresh()->start_date->toDateString())->toBe('2027-04-01')
        ->and($fiscalYear->fresh()->end_date->toDateString())->toBe('2028-03-31');
});

it('keeps only one fiscal year active when creating, editing, or toggling', function () {
    $first = FiscalYear::create(fiscalYearData(['year' => 2026, 'is_active' => true]));

    Livewire::test(CreateFiscalYear::class)
        ->fillForm(fiscalYearData(['year' => 2027, 'is_active' => true]))
        ->call('create')
        ->assertHasNoFormErrors();

    $second = FiscalYear::where('year', 2027)->firstOrFail();
    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->is_active)->toBeTrue();

    Livewire::test(EditFiscalYear::class, ['record' => $first->id])
        ->fillForm(fiscalYearData(['year' => 2026, 'is_active' => true]))
        ->call('save')
        ->assertHasNoFormErrors();

    expect($first->fresh()->is_active)->toBeTrue()
        ->and($second->fresh()->is_active)->toBeFalse();

    Livewire::test(ListFiscalYears::class)
        ->call('updateTableColumnState', 'is_active', (string) $second->id, true)
        ->assertSuccessful();

    expect($first->fresh()->is_active)->toBeFalse()
        ->and($second->fresh()->is_active)->toBeTrue();
});

it('protects fiscal years referenced by targets from single and bulk deletion', function () {
    $linkedYear = FiscalYear::create(fiscalYearData(['year' => 2026]));
    $emptyYear = FiscalYear::create(fiscalYearData(['year' => 2027]));
    createFiscalYearTarget($linkedYear);

    expect($this->administrator->can('delete', $linkedYear))->toBeFalse()
        ->and($this->administrator->can('delete', $emptyYear))->toBeTrue();

    Livewire::test(EditFiscalYear::class, ['record' => $linkedYear->id])
        ->assertActionHidden('delete');

    Livewire::test(ListFiscalYears::class)
        ->callTableBulkAction('delete', [$linkedYear, $emptyYear]);

    $this->assertDatabaseHas('fiscal_years', ['id' => $linkedYear->id]);
    $this->assertDatabaseMissing('fiscal_years', ['id' => $emptyYear->id]);
});

it('enforces fiscal year permissions and denies non-admin access to the Admin panel resource', function () {
    $fiscalYear = FiscalYear::create(fiscalYearData());
    $srmUser = User::factory()->create();
    $srmUser->assignRole('SRMU');
    $budgetUser = User::factory()->create();
    $budgetUser->assignRole('BUDGET');

    expect($this->administrator->can('viewAny', FiscalYear::class))->toBeTrue()
        ->and($this->administrator->can('create', FiscalYear::class))->toBeTrue()
        ->and($this->administrator->can('update', $fiscalYear))->toBeTrue()
        ->and($this->administrator->can('delete', $fiscalYear))->toBeTrue()
        ->and($srmUser->can('update', $fiscalYear))->toBeTrue()
        ->and($budgetUser->can('update', $fiscalYear))->toBeFalse();

    $this->actingAs($srmUser)->get('/admin/fiscal-years')->assertForbidden();
    $this->actingAs($budgetUser)->get('/admin/fiscal-years')->assertForbidden();
});
