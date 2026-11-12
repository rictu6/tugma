<?php

use App\Models\Allotment;
use App\Models\AllotmentEntry;
use App\Models\Accomplishment;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Office;
use App\Models\OutcomeArea;
use App\Models\PerformanceIndicator;
use App\Models\Program;
use App\Models\Target;
use App\Models\Uacs;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

test('defines bidirectional relationships with the expected foreign keys', function () {
    $relationships = [
        [FiscalYear::class, 'targets', HasMany::class, Target::class, 'fiscal_year_id'],
        [Target::class, 'fiscalYear', BelongsTo::class, FiscalYear::class, 'fiscal_year_id'],
        [Target::class, 'performanceIndicators', HasMany::class, PerformanceIndicator::class, 'target_id'],
        [OutcomeArea::class, 'programs', HasMany::class, Program::class, 'outcome_area_id'],
        [Program::class, 'outcomeArea', BelongsTo::class, OutcomeArea::class, 'outcome_area_id'],
        [Program::class, 'performanceIndicators', HasMany::class, PerformanceIndicator::class, 'program_id'],
        [Office::class, 'performanceIndicators', HasMany::class, PerformanceIndicator::class, 'office_id'],
        [PerformanceIndicator::class, 'program', BelongsTo::class, Program::class, 'program_id'],
        [PerformanceIndicator::class, 'target', BelongsTo::class, Target::class, 'target_id'],
        [PerformanceIndicator::class, 'office', BelongsTo::class, Office::class, 'office_id'],
        [PerformanceIndicator::class, 'accomplishments', HasMany::class, Accomplishment::class, 'performance_indicator_id'],
        [PerformanceIndicator::class, 'allotments', HasMany::class, Allotment::class, 'performance_indicator_id'],
        [Allotment::class, 'performanceIndicator', BelongsTo::class, PerformanceIndicator::class, 'performance_indicator_id'],
        [Allotment::class, 'expenses', HasMany::class, Expense::class, 'allotment_id'],
        [Allotment::class, 'allotmentEntries', HasManyThrough::class, AllotmentEntry::class, 'expense_id'],
        [Expense::class, 'allotment', BelongsTo::class, Allotment::class, 'allotment_id'],
        [Expense::class, 'uacs', BelongsTo::class, Uacs::class, 'uacs_id'],
        [Expense::class, 'allotmentEntries', HasMany::class, AllotmentEntry::class, 'expense_id'],
        [AllotmentEntry::class, 'expense', BelongsTo::class, Expense::class, 'expense_id'],
        [Accomplishment::class, 'performanceIndicator', BelongsTo::class, PerformanceIndicator::class, 'performance_indicator_id'],
        [Uacs::class, 'expenses', HasMany::class, Expense::class, 'uacs_id'],
    ];

    foreach ($relationships as [$modelClass, $method, $relationshipClass, $relatedModelClass, $foreignKey]) {
        $relationship = (new $modelClass)->{$method}();

        expect($relationship)->toBeInstanceOf($relationshipClass);
        expect($relationship->getRelated())->toBeInstanceOf($relatedModelClass);
        expect($relationship->getForeignKeyName())->toBe($foreignKey);
    }
});
