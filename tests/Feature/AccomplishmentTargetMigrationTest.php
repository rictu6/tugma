<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $officeId = DB::table('offices')->insertGetId(['code' => 'OFF', 'name' => 'Office', 'division' => 'Division']);
    $fiscalYearId = DB::table('fiscal_years')->insertGetId(['year' => 2026, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $targetId = DB::table('targets')->insertGetId(['fiscal_year_id' => $fiscalYearId]);
    $outcomeAreaId = DB::table('outcome_areas')->insertGetId(['name' => 'Outcome', 'short_code' => 'OUT']);
    $programId = DB::table('programs')->insertGetId(['outcome_area_id' => $outcomeAreaId, 'name' => 'Program']);
    $indicatorId = DB::table('performance_indicators')->insertGetId([
        'target_id' => $targetId,
        'program_id' => $programId,
        'office_id' => $officeId,
        'code' => 'PI',
        'description' => 'Indicator',
    ]);
    $this->accomplishmentId = DB::table('accomplishments')->insertGetId([
        'performance_indicator_id' => $indicatorId,
        'type' => 'fixed',
        'target_quarter' => 1,
        'target_semi_annual' => 1,
    ]);
    $this->migration = require database_path('migrations/2026_11_12_000001_add_target_to_accomplishments_table.php');
});

it('adds a nullable target while preserving existing accomplishments', function () {
    Schema::table('accomplishments', function (Blueprint $table) {
        $table->dropColumn('target');
    });

    $this->migration->up();

    expect(Schema::hasColumn('accomplishments', 'target'))->toBeTrue()
        ->and(DB::table('accomplishments')->where('id', $this->accomplishmentId)->first()->target)->toBeNull();
    DB::table('accomplishments')->where('id', $this->accomplishmentId)->update(['target' => '50%']);
    expect(DB::table('accomplishments')->where('id', $this->accomplishmentId)->value('target'))->toBe('50%');
});

it('preserves an existing target column and blocks unsafe rollback', function () {
    DB::table('accomplishments')->where('id', $this->accomplishmentId)->update(['target' => 'Existing target']);

    $this->migration->up();

    expect(DB::table('accomplishments')->where('id', $this->accomplishmentId)->value('target'))->toBe('Existing target')
        ->and(fn () => $this->migration->down())->toThrow(RuntimeException::class);
    expect(DB::table('accomplishments')->where('id', $this->accomplishmentId)->value('target'))->toBe('Existing target');

    Schema::table('accomplishments', function (Blueprint $table) {
        $table->dropColumn('target');
    });
    $this->migration->down();
    expect(Schema::hasColumn('accomplishments', 'target'))->toBeFalse();
});
