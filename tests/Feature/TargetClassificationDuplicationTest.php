<?php

use App\Filament\App\Resources\Targets\Pages\ManageTargets;
use App\Models\FiscalYear;
use App\Models\Office;
use App\Models\OutcomeArea;
use App\Models\PerformanceIndicator;
use App\Models\Program;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $this->year = FiscalYear::create(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $this->nextYear = FiscalYear::create(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
    $this->target = Target::create(['fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'status' => 'approved']);
    $this->area = OutcomeArea::create(['name' => 'Outcome', 'short_code' => 'OUT']);
    $this->program = Program::create(['outcome_area_id' => $this->area->id, 'name' => 'Program']);
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $this->indicator = PerformanceIndicator::create([
        'target_id' => $this->target->id, 'program_id' => $this->program->id, 'office_id' => $office->id,
        'code' => 'PI', 'description' => 'Indicator',
    ]);
    $this->indicator->accomplishments()->create([
        'type' => 'fixed', 'target_quarter' => 1, 'target_semi_annual' => 1, 'tag' => 'met',
    ]);
});

it('copies only target classifications into the selected year and OPB type', function (string $opbType) {
    $extraArea = OutcomeArea::create(['name' => 'Extra area', 'short_code' => 'EXTRA']);
    $extraProgram = Program::create(['outcome_area_id' => $extraArea->id, 'name' => 'Extra program']);
    $this->target->outcomeAreas()->attach($extraArea);
    $this->target->programs()->attach($extraProgram);

    Livewire::test(ManageTargets::class)->callTableAction('duplicate', $this->target, [
        'fiscal_year_id' => $this->nextYear->id, 'opb_type' => $opbType,
    ])->assertHasNoTableActionErrors();

    $copy = Target::where('fiscal_year_id', $this->nextYear->id)->firstOrFail();
    expect($copy->status)->toBe('draft')
        ->and($copy->opb_type)->toBe($opbType)
        ->and($copy->outcomeAreas()->orderBy('outcome_areas.id')->pluck('outcome_areas.id')->all())->toBe([$this->area->id, $extraArea->id])
        ->and($copy->programs()->orderBy('programs.id')->pluck('programs.id')->all())->toBe([$this->program->id, $extraProgram->id])
        ->and($copy->performanceIndicators()->count())->toBe(0)
        ->and(PerformanceIndicator::count())->toBe(1)
        ->and($this->indicator->accomplishments()->count())->toBe(1)
        ->and(OutcomeArea::count())->toBe(2)
        ->and(Program::count())->toBe(2);

    $thirdYear = FiscalYear::create(['year' => 2028, 'start_date' => '2028-01-01', 'end_date' => '2028-12-31']);
    Livewire::test(ManageTargets::class)->callTableAction('duplicate', $copy, [
        'fiscal_year_id' => $thirdYear->id, 'opb_type' => $opbType,
    ])->assertHasNoTableActionErrors();
    $thirdCopy = Target::where('fiscal_year_id', $thirdYear->id)->firstOrFail();
    expect($thirdCopy->programs()->count())->toBe(2)
        ->and($thirdCopy->outcomeAreas()->count())->toBe(2)
        ->and($thirdCopy->performanceIndicators()->count())->toBe(0);
})->with(['current', 'continuing']);

it('backfills distinct classifications for existing indicators', function () {
    PerformanceIndicator::create([
        'target_id' => $this->target->id, 'program_id' => $this->program->id, 'office_id' => $this->indicator->office_id,
        'code' => 'PI-2', 'description' => 'Second indicator',
    ]);
    $migration = require database_path('migrations/2026_11_10_000001_create_target_classification_tables.php');
    $migration->down();
    $migration->up();

    expect($this->target->programs()->pluck('programs.id')->all())->toBe([$this->program->id])
        ->and($this->target->outcomeAreas()->pluck('outcome_areas.id')->all())->toBe([$this->area->id]);
});

it('allows editing target classifications without creating indicators', function () {
    $extraArea = OutcomeArea::create(['name' => 'Extra area', 'short_code' => 'EXTRA']);
    $extraProgram = Program::create(['outcome_area_id' => $extraArea->id, 'name' => 'Extra program']);

    Livewire::test(ManageTargets::class)->callTableAction('edit', $this->target, [
        'fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'status' => 'approved',
        'outcomeAreas' => [$this->area->id, $extraArea->id], 'programs' => [$this->program->id, $extraProgram->id],
    ])->assertHasNoTableActionErrors();

    expect($this->target->outcomeAreas()->count())->toBe(2)
        ->and($this->target->programs()->count())->toBe(2)
        ->and($this->target->performanceIndicators()->count())->toBe(1);
});

it('attaches classifications when an indicator moves to another program', function () {
    $otherArea = OutcomeArea::create(['name' => 'Another area', 'short_code' => 'OTHER']);
    $otherProgram = Program::create(['outcome_area_id' => $otherArea->id, 'name' => 'Another program']);
    $this->indicator->update(['program_id' => $otherProgram->id]);

    expect($this->target->programs()->whereKey($otherProgram->id)->exists())->toBeTrue()
        ->and($this->target->outcomeAreas()->whereKey($otherArea->id)->exists())->toBeTrue();
});

it('allows authorized target duplication without indicator creation access', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['targets.view', 'targets.create', 'programs.view', 'outcome-areas.view']);
    $this->actingAs($user);

    Livewire::test(ManageTargets::class)->assertTableActionVisible('duplicate', $this->target)
        ->callTableAction('duplicate', $this->target, ['fiscal_year_id' => $this->nextYear->id, 'opb_type' => 'current'])
        ->assertHasNoTableActionErrors();
    expect(Target::where('fiscal_year_id', $this->nextYear->id)->firstOrFail()->programs()->count())->toBe(1);
});

it('requires access to classifications and validates the destination', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['targets.view', 'targets.create', 'programs.view']);
    $this->actingAs($user);
    Livewire::test(ManageTargets::class)->assertTableActionHidden('duplicate', $this->target);

    $user->givePermissionTo('outcome-areas.view');
    Livewire::test(ManageTargets::class)->callTableAction('duplicate', $this->target, [
        'fiscal_year_id' => 99999, 'opb_type' => 'invalid',
    ])->assertHasTableActionErrors(['fiscal_year_id', 'opb_type']);
    expect(Target::count())->toBe(1);
});

it('requires selected programs to belong to selected areas and retains indicator programs', function () {
    Livewire::test(ManageTargets::class)->callTableAction('edit', $this->target, [
        'fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'status' => 'approved',
        'outcomeAreas' => [], 'programs' => [$this->program->id],
    ])->assertHasTableActionErrors(['programs']);

    Livewire::test(ManageTargets::class)->callTableAction('edit', $this->target, [
        'fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'status' => 'approved',
        'outcomeAreas' => [$this->area->id], 'programs' => [],
    ])->assertHasTableActionErrors(['programs']);
});
