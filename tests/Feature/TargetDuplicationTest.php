<?php

use App\Filament\App\Resources\PerformanceIndicators\Pages\ListPerformanceIndicators;
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
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $sourceYear = FiscalYear::create(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $this->destinationYear = FiscalYear::create(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
    $this->source = Target::create(['fiscal_year_id' => $sourceYear->id, 'opb_type' => 'current', 'status' => 'approved']);
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $outcome = OutcomeArea::create(['name' => 'Outcome', 'short_code' => 'OUT']);
    $program = Program::create(['outcome_area_id' => $outcome->id, 'name' => 'Program']);
    $this->indicator = PerformanceIndicator::create([
        'target_id' => $this->source->id,
        'program_id' => $program->id,
        'office_id' => $office->id,
        'code' => 'PI',
        'description' => 'Indicator',
    ]);
    $this->indicator->accomplishments()->create([
        'type' => 'fixed',
        'unit' => 'number',
        'target_quarter' => 1,
        'target_semi_annual' => 1,
        'target' => 'At least 10 activities',
        'date_accomplished' => '2026-02-01',
        'actual' => '10',
        'tag' => 'deferred',
        'actual_accomplishment_description' => 'Completed',
        'reason' => 'Deferred by CO',
        'remarks' => 'Previous year',
    ]);
    $this->indicator->allotments()->create([
        'sub_allotment_number' => 'A',
        'year' => 2025,
        'authorization_type' => 'Test',
        'amount' => 100,
    ]);
});

it('duplicates target classifications without indicators or results', function (string $opbType) {
    Livewire::test(ManageTargets::class)->callTableAction('duplicate', $this->source, [
        'fiscal_year_id' => $this->destinationYear->id,
        'opb_type' => $opbType,
    ])->assertHasNoTableActionErrors();

    $copy = Target::where('fiscal_year_id', $this->destinationYear->id)->firstOrFail();
    expect($copy->status)->toBe('draft')
        ->and($copy->opb_type)->toBe($opbType)
        ->and($copy->programs()->pluck('programs.id')->all())->toBe([$this->indicator->program_id])
        ->and($copy->outcomeAreas()->pluck('outcome_areas.id')->all())->toBe([$this->indicator->program->outcome_area_id])
        ->and($copy->performanceIndicators()->count())->toBe(0);
})->with(['continuing', 'current']);

it('calculates accomplishment status from the target fiscal year instead of allotments', function () {
    $period = $this->indicator->accomplishments()->firstOrFail();
    expect($period->performanceStatus())->toBe('On Time');
    $this->indicator->allotments()->delete();
    expect($period->fresh()->performanceStatus())->toBe('On Time');
});

it('shows per-indicator actual and target totals in the targets column', function (array $rows, string|array $expected, string|array|null $color) {
    $this->indicator->accomplishments()->delete();

    foreach ($rows as [$unit, $actual, $target]) {
        $this->indicator->accomplishments()->create([
            'type' => 'fixed',
            'unit' => $unit,
            'target_quarter' => 1,
            'target_semi_annual' => 1,
            'actual' => $actual,
            'target' => $target,
        ]);
    }

    $other = PerformanceIndicator::create([
        'target_id' => $this->source->id,
        'program_id' => $this->indicator->program_id,
        'office_id' => $this->indicator->office_id,
        'code' => 'OTHER',
        'description' => 'Other indicator',
    ]);
    $other->accomplishments()->create([
        'type' => 'fixed', 'unit' => 'number', 'target_quarter' => 1, 'target_semi_annual' => 1,
        'actual' => '100', 'target' => '200',
    ]);

    $table = Livewire::test(ListPerformanceIndicators::class)
        ->assertTableColumnStateSet('accomplishments_count', $expected, $this->indicator)
        ->assertTableColumnStateSet('accomplishments_count', '100.00 / 200.00', $other);

    $record = $table->instance()->getTableRecord((string) $this->indicator->getKey());
    $column = $table->instance()->getTable()->getColumn('accomplishments_count')->record($record);

    expect($record->relationLoaded('accomplishments'))->toBeTrue();

    foreach ((array) $expected as $index => $value) {
        $table->assertSee($value);
        expect($column->getColor($value))->toBe(is_array($color) ? $color[$index] : $color);
    }
})->with([
    [[['number', '3', '5'], ['number', '2', '4'], ['percentage', '100', '100'], ['number', 'text', 'text']], ['5.00 / 9.00', '100.00% / 100.00%'], ['danger', 'success']],
    [[['number', '5', '5']], '5.00 / 5.00', 'success'],
    [[['number', '6', '5']], '6.00 / 5.00', 'success'],
    [[['number', '0', '0']], '0.00 / 0.00', 'success'],
    [[['number', null, '5']], '- / 5.00', null],
    [[['number', '5', null]], '5.00 / -', null],
    [[['percentage', '50', '50']], '50.00% / 50.00%', 'success'],
    [[['percentage', '30%', '60%'], ['percentage', '90', '80'], ['percentage', null, null]], '60.00% / 70.00%', 'danger'],
    [[['percentage', '80', '50'], ['percentage', '100', '90']], '90.00% / 70.00%', 'success'],
    [[['percentage', '0', '0'], ['percentage', null, '10'], ['percentage', 'text', 'text']], '0.00% / 5.00%', 'danger'],
    [[['percentage', '90', null]], '90.00% / -', null],
    [[['percentage', null, '80']], '- / 80.00%', null],
    [[['percentage', '50%%', '50%']], '- / 50.00%', null],
    [[], '- / -', null],
]);

it('prevents deleting reusable classifications', function () {
    $program = $this->indicator->program;
    $user = auth()->user();
    expect($user->can('delete', $program))->toBeFalse()
        ->and($user->can('delete', $program->outcomeArea))->toBeFalse();
});

it('prevents moving programs between outcome areas', function () {
    $outcome = OutcomeArea::create(['name' => 'Another outcome', 'short_code' => 'OTHER']);
    $program = $this->indicator->program;

    expect(fn () => $program->update(['outcome_area_id' => $outcome->id]))->toThrow(ValidationException::class);
    expect($program->fresh()->outcome_area_id)->not->toBe($outcome->id);
});

it('hides duplication when the user cannot view classifications', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['targets.view', 'targets.create', 'programs.view']);
    $this->actingAs($user);

    Livewire::test(ManageTargets::class)->assertTableActionHidden('duplicate', $this->source);
});

it('rejects invalid destination types and fiscal years', function () {
    Livewire::test(ManageTargets::class)->callTableAction('duplicate', $this->source, [
        'fiscal_year_id' => 99999,
        'opb_type' => 'invalid',
    ])->assertHasTableActionErrors(['fiscal_year_id', 'opb_type']);

    expect(Target::count())->toBe(1);
});

it('groups targets by fiscal year and filters by OPB type', function () {
    $continuing = Target::create(['fiscal_year_id' => $this->source->fiscal_year_id, 'opb_type' => 'continuing', 'status' => 'draft']);
    $nextYear = Target::create(['fiscal_year_id' => $this->destinationYear->id, 'opb_type' => 'current', 'status' => 'draft']);

    $table = Livewire::test(ManageTargets::class)
        ->assertCanSeeTableRecords([$this->source, $continuing, $nextYear])
        ->assertSee('Fiscal year: 2026')
        ->assertSee('Fiscal year: 2027');

    expect($table->instance()->getTableGrouping()->getId())->toBe('fiscalYear.year');

    $table->filterTable('opb_type', 'current')
        ->assertCanSeeTableRecords([$this->source, $nextYear])
        ->assertCanNotSeeTableRecords([$continuing]);

    $table->filterTable('opb_type', 'continuing')
        ->assertCanSeeTableRecords([$continuing])
        ->assertCanNotSeeTableRecords([$this->source, $nextYear]);

    $table->filterTable('opb_type', null)
        ->set('tableGrouping', 'opb_type:asc')
        ->assertCanSeeTableRecords([$this->source, $continuing, $nextYear])
        ->assertSee('OPB type: Current')
        ->assertSee('OPB type: Continuing');

    expect($table->instance()->getTableGrouping()->getId())->toBe('opb_type');
});

it('groups indicators by program or outcome area', function () {
    $outcome = OutcomeArea::create(['name' => 'Second outcome', 'short_code' => 'SECOND']);
    $program = Program::create(['outcome_area_id' => $outcome->id, 'name' => 'Second program']);
    $indicator = PerformanceIndicator::create([
        'target_id' => $this->source->id,
        'program_id' => $program->id,
        'office_id' => $this->indicator->office_id,
        'code' => 'PI-2',
        'description' => 'Second indicator',
    ]);

    $table = Livewire::test(ListPerformanceIndicators::class)
        ->assertCanSeeTableRecords([$this->indicator, $indicator])
        ->assertSee('Outcome — Program')
        ->assertSee('Second outcome — Second program');

    expect($table->instance()->getTableGrouping()->getId())->toBe('program_id');

    $table->set('tableGrouping', 'program.outcome_area_id:asc')
        ->assertCanSeeTableRecords([$this->indicator, $indicator])
        ->assertSee('Outcome area: Outcome')
        ->assertSee('Outcome area: Second outcome');

    expect($table->instance()->getTableGrouping()->getId())->toBe('program.outcome_area_id');
});
