<?php

use App\Filament\App\Resources\PerformanceIndicators\Pages\EditPerformanceIndicator;
use App\Filament\App\Resources\PerformanceIndicators\RelationManagers\AccomplishmentsRelationManager;
use App\Models\Accomplishment;
use App\Models\PerformanceIndicator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    $officeId = DB::table('offices')->insertGetId(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $fiscalYearId = DB::table('fiscal_years')->insertGetId(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $targetId = DB::table('targets')->insertGetId(['fiscal_year_id' => $fiscalYearId]);
    $outcomeAreaId = DB::table('outcome_areas')->insertGetId(['name' => 'Outcome', 'short_code' => 'OUT']);
    $programId = DB::table('programs')->insertGetId(['outcome_area_id' => $outcomeAreaId, 'name' => 'Program']);

    $this->indicator = PerformanceIndicator::create([
        'target_id' => $targetId,
        'program_id' => $programId,
        'office_id' => $officeId,
        'code' => 'PI',
        'description' => 'Indicator',
    ]);
});

it('groups target and accomplishment fields without changing their validation', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $record = $this->indicator->accomplishments()->create([
        'type' => 'fixed', 'unit' => 'number', 'target_quarter' => 1, 'target_semi_annual' => 1, 'target' => '10',
    ]);

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->mountTableAction('edit', $record)
        ->assertSee('Target')
        ->assertSee('Accomplishment')
        ->assertSee('Target quarter')
        ->assertSee('Date accomplished');

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->callTableAction('edit', $record, [
        'type' => 'fixed', 'unit' => 'number', 'target_quarter' => 3, 'target_semi_annual' => 1,
        'target' => '10', 'tag' => 'deferred', 'reason' => null,
    ])->assertHasTableActionErrors(['target_semi_annual', 'reason']);
});

it('shows and requires a reason only for deferred tags on create and edit', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);
    $period = ['target_quarter' => 1, 'target_semi_annual' => 1, 'target' => '10'];
    $manager = fn () => Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);

    $manager()->callTableAction('create', data: [
        'type' => 'fixed', 'unit' => 'number',
        'periods' => [[...$period, 'tag' => 'deferred', 'reason' => null]],
    ])->assertHasTableActionErrors();
    expect($this->indicator->accomplishments()->count())->toBe(0);

    $manager()->callTableAction('create', data: [
        'type' => 'fixed', 'unit' => 'number',
        'periods' => [[...$period, 'tag' => 'deferred', 'reason' => 'Deferred by CO']],
    ])->assertHasNoTableActionErrors();
    $record = $this->indicator->accomplishments()->firstOrFail();
    expect($record->tag)->toBe('deferred')->and($record->reason)->toBe('Deferred by CO');

    $manager()->mountTableAction('edit', $record)
        ->assertFormFieldVisible('reason')
        ->setTableActionData(['tag' => 'met'])
        ->assertFormFieldHidden('reason')
        ->setTableActionData(['tag' => 'deferred'])
        ->assertFormFieldVisible('reason');

    $data = ['type' => 'fixed', 'unit' => 'number', ...$period];
    $manager()->callTableAction('edit', $record, [...$data, 'tag' => 'deferred', 'reason' => null])
        ->assertHasTableActionErrors(['reason']);
    $manager()->callTableAction('edit', $record, [...$data, 'tag' => 'deferred', 'reason' => 'No funds downloaded'])
        ->assertHasNoTableActionErrors();
    expect($record->fresh()->reason)->toBe('No funds downloaded');
    $manager()->callTableAction('edit', $record, [...$data, 'tag' => 'met', 'reason' => null])
        ->assertHasNoTableActionErrors();
    expect($record->fresh()->tag)->toBe('met');
});

it('creates and edits nullable accomplishment tags', function () {

    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);
    $period = ['target_quarter' => 1, 'target_semi_annual' => 1, 'target' => '10'];

    $manager->callTableAction('create', data: [
        'type' => 'fixed', 'unit' => 'number',
        'periods' => [[...$period, 'tag' => 'invalid']],
    ])->assertHasTableActionErrors();
    expect($this->indicator->accomplishments()->count())->toBe(0);

    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);
    $manager->callTableAction('create', data: [
        'type' => 'fixed', 'unit' => 'number',
        'periods' => [[...$period, 'tag' => 'exceeded'], [...$period, 'tag' => null]],
    ])->assertHasNoTableActionErrors();

    $record = $this->indicator->accomplishments()->where('tag', 'exceeded')->firstOrFail();
    expect($this->indicator->accomplishments()->whereNull('tag')->count())->toBe(1)
        ->and(Schema::hasColumn('accomplishments', 'tag'))->toBeTrue()
        ->and(Schema::hasColumn('performance_indicators', 'tag'))->toBeFalse();

    $data = ['type' => 'fixed', 'unit' => 'number', ...$period];
    $manager->callTableAction('edit', $record, [...$data, 'tag' => 'invalid'])
        ->assertHasTableActionErrors(['tag']);
    expect($record->fresh()->tag)->toBe('exceeded');
    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);
    $manager->callTableAction('edit', $record, [...$data, 'tag' => 'moved'])
        ->assertHasNoTableActionErrors();
    expect($record->fresh()->tag)->toBe('moved');
    $manager->callTableAction('edit', $record, [...$data, 'tag' => null])
        ->assertHasNoTableActionErrors();
    expect($record->fresh()->tag)->toBeNull();
});

it('displays accomplishment tag badges and permits only supported database values', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $record = $this->indicator->accomplishments()->create([
        'type' => 'fixed', 'target_quarter' => 1, 'target_semi_annual' => 1,
    ]);
    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);
    $column = $manager->instance()->getTable()->getColumn('tag');
    expect($column->isBadge())->toBeTrue();

    foreach (['met' => 'success', 'unmet' => 'danger', 'cont' => 'warning', 'deferred' => 'warning', 'moved' => 'warning', 'na' => 'gray', 'exceeded' => 'success'] as $tag => $color) {
        $record->update(['tag' => $tag]);
        expect($record->fresh()->tag)->toBe($tag)
            ->and($column->getColor($tag))->toBe($color);
        Livewire::test(AccomplishmentsRelationManager::class, [
            'ownerRecord' => $this->indicator,
            'pageClass' => EditPerformanceIndicator::class,
        ])->assertSee(Accomplishment::tagOptions()[$tag]);
    }

    expect($column->getColor(null))->toBe('gray')
        ->and(fn () => $record->update(['tag' => 'invalid']))->toThrow(\Illuminate\Database\QueryException::class);
});

it('preserves existing tags and refuses to roll back deferred tags', function () {
    $record = $this->indicator->accomplishments()->create([
        'type' => 'fixed', 'target_quarter' => 1, 'target_semi_annual' => 1, 'tag' => 'deferred',
    ]);
    $migration = require database_path('migrations/2026_11_09_000003_add_deferred_accomplishment_tag.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($record->fresh()->tag)->toBe('deferred');

    $record->update(['tag' => 'met']);
    $migration->down();
    expect($record->fresh()->tag)->toBe('met')
        ->and(fn () => $record->update(['tag' => 'deferred']))->toThrow(\Illuminate\Database\QueryException::class);

    $migration->up();
    expect($record->fresh()->tag)->toBe('met');
    $record->update(['tag' => 'deferred']);
    expect($record->fresh()->tag)->toBe('deferred');
});

it('creates and edits optional accomplishment targets', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);

    $manager->callTableAction('create', data: [
        'type' => 'fixed',
        'unit' => 'number',
        'periods' => [
            ['target_quarter' => 1, 'target_semi_annual' => 1, 'target' => 'At least 10 activities'],
            ['target_quarter' => 2, 'target_semi_annual' => 1, 'target' => null],
        ],
    ])->assertHasNoTableActionErrors();

    $first = $this->indicator->accomplishments()->where('target_quarter', 1)->firstOrFail();
    $second = $this->indicator->accomplishments()->where('target_quarter', 2)->firstOrFail();

    expect($first->target)->toBe('At least 10 activities')
        ->and($second->target)->toBeNull();

    $manager->assertSee('At least 10 activities')
        ->callTableAction('edit', $first, [
            'type' => 'fixed',
            'unit' => 'number',
            'target_quarter' => 1,
            'target_semi_annual' => 1,
            'target' => 'Revised target',
        ])->assertHasNoTableActionErrors();

    expect($first->fresh()->target)->toBe('Revised target');
});

it('shows actual comparison colors and icons', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $manager = Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ]);
    $column = $manager->instance()->getTable()->getColumn('actual');

    foreach ([
        ['4', '5', 'danger', 'heroicon-o-exclamation-circle'],
        ['5', '5', 'success', 'heroicon-o-check-circle'],
        ['6', '5', 'success', 'heroicon-o-check-circle'],
        [null, '5', null, null],
        ['5', 'not numeric', null, null],
    ] as [$actual, $target, $color, $icon]) {
        $record = new Accomplishment(['actual' => $actual, 'target' => $target, 'unit' => 'number']);
        $column->record($record);

        expect($column->getColor($actual))->toBe($color)
            ->and($column->getIcon($actual))->toBe($icon);
    }
});

it('summarizes only numeric targets with number units', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    foreach ([
        ['number', '10.25'],
        ['number', '2.345'],
        ['number', 'At least 10 activities'],
        ['number', null],
        ['percentage', '50'],
        [null, '100'],
    ] as [$unit, $target]) {
        $this->indicator->accomplishments()->create([
            'type' => 'fixed',
            'unit' => $unit,
            'target_quarter' => 1,
            'target_semi_annual' => 1,
            'target' => $target,
        ]);
    }

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->assertTableColumnSummarySet('target', 'total', 12.60);
});

it('bulk deletes selected accomplishments for authorized users', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $selected = $this->indicator->accomplishments()->create(['type' => 'fixed', 'target_quarter' => 1, 'target_semi_annual' => 1]);
    $unselected = $this->indicator->accomplishments()->create(['type' => 'fixed', 'target_quarter' => 2, 'target_semi_annual' => 1]);

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->callTableBulkAction('delete', [$selected]);

    $this->assertDatabaseMissing('accomplishments', ['id' => $selected->id]);
    $this->assertDatabaseHas('accomplishments', ['id' => $unselected->id]);
});

it('hides bulk deletion without the delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['accomplishments.view', 'performance-indicators.view']);
    $this->actingAs($user);

    expect($user->can('deleteAny', Accomplishment::class))->toBeFalse();

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->assertTableBulkActionHidden('delete');
});

it('does not bulk delete records denied by the individual delete policy', function () {
    $user = User::factory()->create();
    $user->assignRole('RICTU');
    $this->actingAs($user);

    $allowed = $this->indicator->accomplishments()->create(['type' => 'fixed', 'target_quarter' => 1, 'target_semi_annual' => 1]);
    $denied = $this->indicator->accomplishments()->create(['type' => 'fixed', 'target_quarter' => 2, 'target_semi_annual' => 1]);
    Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'delete' && ($arguments[0] ?? null) instanceof Accomplishment && $arguments[0]->is($denied) ? false : null);

    Livewire::test(AccomplishmentsRelationManager::class, [
        'ownerRecord' => $this->indicator,
        'pageClass' => EditPerformanceIndicator::class,
    ])->callTableBulkAction('delete', [$allowed, $denied]);

    $this->assertDatabaseMissing('accomplishments', ['id' => $allowed->id]);
    $this->assertDatabaseHas('accomplishments', ['id' => $denied->id]);
});
