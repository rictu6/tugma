<?php

use App\Filament\App\Pages\Dashboard;
use App\Filament\App\Widgets\QuarterlyPerformance;
use App\Models\FiscalYear;
use App\Models\Office;
use App\Models\OutcomeArea;
use App\Models\PerformanceIndicator;
use App\Models\Program;
use App\Models\Target;
use App\Models\User;
use App\Services\QuarterlyPerformanceReport;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->user = User::factory()->create();
    $this->user->assignRole('RICTU');
    $this->actingAs($this->user);

    $this->year = FiscalYear::create(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $this->otherYear = FiscalYear::create(['year' => 2027, 'start_date' => '2027-01-01', 'end_date' => '2027-12-31']);
    $office = Office::create(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $area = OutcomeArea::create(['name' => 'Outcome', 'short_code' => 'OUT']);
    $program = Program::create(['outcome_area_id' => $area->id, 'name' => 'Program']);

    $this->makeIndicator = function (FiscalYear $year, string $opb) use ($office, $program): PerformanceIndicator {
        $target = Target::create(['fiscal_year_id' => $year->id, 'opb_type' => $opb]);

        return PerformanceIndicator::create([
            'target_id' => $target->id, 'program_id' => $program->id, 'office_id' => $office->id,
            'code' => 'PI', 'description' => 'Indicator',
        ]);
    };

    $this->addPeriod = function (PerformanceIndicator $indicator, int $quarter, ?string $tag = null): void {
        $indicator->accomplishments()->create([
            'type' => 'fixed', 'target_quarter' => $quarter, 'target_semi_annual' => $quarter <= 2 ? 1 : 2,
            'tag' => $tag,
        ]);
    };
});

it('counts quarterly periods and calculates compliance', function () {
    $indicator = ($this->makeIndicator)($this->year, 'current');
    foreach (['met', 'unmet', 'exceeded', 'moved', 'cont', 'na', null] as $tag) {
        ($this->addPeriod)($indicator, 1, $tag);
    }
    ($this->addPeriod)($indicator, 1, 'deferred');
    ($this->addPeriod)($indicator, 2, 'met');
    ($this->addPeriod)($indicator, 2, 'cont');
    ($this->addPeriod)($indicator, 2, 'deferred');
    ($this->addPeriod)($indicator, 2, 'deferred');
    ($this->addPeriod)($indicator, 3, 'deferred');
    ($this->addPeriod)($indicator, 4, 'exceeded');
    ($this->addPeriod)(($this->makeIndicator)($this->year, 'continuing'), 1, 'met');
    ($this->addPeriod)(($this->makeIndicator)($this->otherYear, 'current'), 1, 'met');

    $report = app(QuarterlyPerformanceReport::class);
    $rows = $report->rows($this->year->id, 'current');
    $cumulative = $report->tables($this->year->id, 'current')['cumulativeRows'];
    expect($rows[0])->toBe([
        'quarter' => 1, 'compliance' => 40.0, 'target' => 8,
        'met' => 1, 'unmet' => 1, 'deferred' => 1, 'na' => 1, 'exceeded' => 1,
    ])
        ->and($rows[1]['compliance'])->toBe(100.0)
        ->and($rows[1]['deferred'])->toBe(2)
        ->and($rows[2]['compliance'])->toBeNull()
        ->and($rows[2]['target'])->toBe(1)
        ->and($rows[2]['deferred'])->toBe(1)
        ->and($rows[3]['compliance'])->toBe(100.0)
        ->and($rows[3]['deferred'])->toBe(0)
        ->and($report->rows($this->year->id, 'continuing')[0]['target'])->toBe(1)
        ->and($report->rows($this->otherYear->id, 'current')[0]['target'])->toBe(1)
        ->and($report->rows(null, null)[0]['target'])->toBe(10)
        ->and($report->rows([], 'current')[0]['target'])->toBe(0)
        ->and($report->rows($this->year->id, 'invalid')[0]['target'])->toBe(0)
        ->and($cumulative)->toBe([
            ['quarter' => 1, 'compliance' => 10.0, 'target' => 8, 'met' => 1, 'unmet' => 1, 'deferred' => 1, 'na' => 1, 'exceeded' => 1],
            ['quarter' => 2, 'compliance' => 25.0, 'target' => 12, 'met' => 2, 'unmet' => 1, 'deferred' => 3, 'na' => 1, 'exceeded' => 1],
            ['quarter' => 3, 'compliance' => 37.5, 'target' => 13, 'met' => 2, 'unmet' => 1, 'deferred' => 4, 'na' => 1, 'exceeded' => 1],
            ['quarter' => 4, 'compliance' => 57.14, 'target' => 14, 'met' => 2, 'unmet' => 1, 'deferred' => 4, 'na' => 1, 'exceeded' => 2],
        ])
        ->and($report->tables($this->year->id, 'continuing')['cumulativeRows'][3]['target'])->toBe(1)
        ->and($report->tables($this->otherYear->id, 'current')['cumulativeRows'][3]['target'])->toBe(1)
        ->and($report->tables([], 'current')['cumulativeRows'][3]['target'])->toBe(0)
        ->and($report->tables($this->year->id, 'invalid')['cumulativeRows'][3]['target'])->toBe(0);
});

it('carries counts through empty quarters and handles an excluded-only denominator', function () {
    $indicator = ($this->makeIndicator)($this->year, 'current');
    ($this->addPeriod)($indicator, 1, 'met');
    ($this->addPeriod)($indicator, 3, 'unmet');
    ($this->addPeriod)(($this->makeIndicator)($this->otherYear, 'current'), 1, 'deferred');

    $report = app(QuarterlyPerformanceReport::class);
    $rows = $report->tables($this->year->id, 'current')['cumulativeRows'];

    expect(array_column($rows, 'target'))->toBe([1, 1, 2, 2])
        ->and(array_column($rows, 'compliance'))->toBe([25.0, 50.0, 37.5, 50.0])
        ->and($report->tables($this->otherYear->id, 'current')['cumulativeRows'][3]['compliance'])->toBeNull();
});

it('filters both reports by office alongside fiscal year and OPB type', function () {
    $otherOffice = Office::create(['code' => 'OTH', 'name' => 'Other office', 'division' => 'Division', 'is_active' => false]);
    $first = ($this->makeIndicator)($this->year, 'current');
    $second = ($this->makeIndicator)($this->year, 'current');
    $second->update(['office_id' => $otherOffice->id]);
    ($this->addPeriod)($first, 1, 'met');
    ($this->addPeriod)($second, 1, 'unmet');
    ($this->addPeriod)($second, 2, 'met');
    ($this->addPeriod)(($this->makeIndicator)($this->year, 'continuing'), 1, 'met');
    ($this->addPeriod)(($this->makeIndicator)($this->otherYear, 'current'), 1, 'met');

    $report = app(QuarterlyPerformanceReport::class);
    $tables = $report->tables($this->year->id, 'current', $otherOffice->id);
    expect($tables['rows'][0]['target'])->toBe(1)
        ->and($tables['rows'][0]['unmet'])->toBe(1)
        ->and($tables['rows'][1]['met'])->toBe(1)
        ->and($tables['cumulativeRows'][1]['target'])->toBe(2)
        ->and($tables['cumulativeRows'][1]['compliance'])->toBe(25.0)
        ->and($report->rows($this->year->id, 'current', $first->office_id)[0]['met'])->toBe(1)
        ->and($report->rows($this->year->id, 'current', '')[0]['target'])->toBe(2)
        ->and($report->tables($this->year->id, 'current', [])['cumulativeRows'][3]['target'])->toBe(0)
        ->and($report->tables($this->year->id, 'current', 'invalid')['rows'][0]['target'])->toBe(0);

    Livewire::test(Dashboard::class)
        ->assertSee('Office')
        ->assertSee('Other office')
        ->set('filters.office_id', $otherOffice->id)
        ->assertSet('filters.office_id', $otherOffice->id);

    $widget = Livewire::test(QuarterlyPerformance::class, [
        'pageFilters' => ['fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'office_id' => $otherOffice->id],
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($widget->html());
    $xpath = new DOMXPath($document);

    expect(trim($xpath->query('(//table)[1]/tbody/tr[1]/td[4]')->item(0)->textContent))->toBe('0')
        ->and(trim($xpath->query('(//table)[2]/tbody/tr[2]/td[2]')->item(0)->textContent))->toBe('2')
        ->and(trim($xpath->query('(//table)[2]/tbody/tr[2]/td[1]')->item(0)->textContent))->toBe('25.00%');

    Livewire::test(QuarterlyPerformance::class, [
        'pageFilters' => ['fiscal_year_id' => $this->year->id, 'opb_type' => 'current', 'office_id' => $first->office_id],
    ])->assertSee('100.00%')->assertSee('50.00%');
});

it('shows and updates the dashboard report with fiscal year and OPB filters', function () {
    ($this->addPeriod)(($this->makeIndicator)($this->year, 'current'), 1, 'met');
    ($this->addPeriod)(($this->makeIndicator)($this->year, 'continuing'), 1, 'unmet');
    ($this->addPeriod)(($this->makeIndicator)($this->otherYear, 'current'), 1, 'unmet');

    $this->get('/')->assertSuccessful()->assertSee('Quarterly OPB performance')->assertSee('Cumulative quarterly OPB performance');

    Livewire::test(Dashboard::class)
        ->assertSet('filters.fiscal_year_id', $this->year->id)
        ->assertSee('OPB Compliance %')
        ->assertSee('50.00%')
        ->set('filters.opb_type', 'current')
        ->assertSet('filters.opb_type', 'current')
        ->set('filters.fiscal_year_id', $this->otherYear->id)
        ->assertSet('filters.fiscal_year_id', $this->otherYear->id);

    Livewire::test(QuarterlyPerformance::class, [
        'pageFilters' => ['fiscal_year_id' => $this->year->id, 'opb_type' => 'current'],
    ])->assertSee('100.00%')
        ->assertSee('25.00%')
        ->assertSee('Cumulative quarterly OPB performance')
        ->assertSeeHtml('>Deferred</th>')
        ->assertDontSeeHtml('>Moved</th>')
        ->assertDontSee('Cumulative Total');

    Livewire::test(QuarterlyPerformance::class, [
        'pageFilters' => ['fiscal_year_id' => $this->otherYear->id, 'opb_type' => 'current'],
    ])->assertSee('0.00%');
});

it('uses the full filter form width', function () {
    $dashboard = Livewire::test(Dashboard::class)->instance();
    $section = $dashboard->getFiltersForm()->getComponents()[0];

    expect($section->getColumnSpan('default'))->toBe('full');
});

it('uses theme colors for compliance ranges and accomplishment counts', function () {
    $values = [0.0, 49.99, 50.0, 84.99, 85.0, 100.0, null];
    $rows = [];

    foreach ($values as $index => $value) {
        $rows[] = [
            'quarter' => $index + 1, 'compliance' => $value, 'target' => 1,
            'met' => 1, 'exceeded' => 0, 'unmet' => 0, 'deferred' => 0, 'na' => 0,
        ];
    }

    $report = Mockery::mock(QuarterlyPerformanceReport::class);
    $report->shouldReceive('tables')->andReturn(['rows' => $rows, 'cumulativeRows' => $rows]);
    app()->instance(QuarterlyPerformanceReport::class, $report);
    $html = Livewire::test(QuarterlyPerformance::class)->html();
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    foreach ([1, 2] as $table) {
        foreach (['danger', 'danger', 'warning', 'warning', 'success', 'success'] as $index => $color) {
            $badge = $xpath->query('(//table)[' . $table . ']/tbody/tr[' . ($index + 1) . ']/td[1]//span')->item(0);
            expect($badge)->not->toBeNull()
                ->and($badge->getAttribute('class'))->toContain('fi-color-' . $color)
                ->and(trim($badge->textContent))->toBe(number_format($values[$index], 2) . '%');
        }

        expect($xpath->query('(//table)[' . $table . ']/tbody/tr[7]/td[1]//span')->length)->toBe(0);
    }

    expect($html)->toContain('var(--success-600)', 'var(--danger-600)', 'var(--warning-600)')
        ->not->toContain('color: green', 'color: red', 'color: orange');
});

it('does not expose the report without permission to view accomplishments', function () {
    $this->user->roles()->firstOrFail()->revokePermissionTo('accomplishments.view');
    $this->user->unsetRelation('roles');

    expect(QuarterlyPerformance::canView())->toBeFalse();
    Livewire::test(Dashboard::class)->assertDontSee('Quarterly OPB performance');
    Livewire::test(QuarterlyPerformance::class)->assertForbidden();
});
