<?php

namespace App\Actions;

use App\Models\Target;
use Illuminate\Support\Facades\DB;

class DuplicateTarget
{
    public function __invoke(Target $source, int $fiscalYearId, string $opbType): Target
    {
        return DB::transaction(function () use ($source, $fiscalYearId, $opbType): Target {
            $target = Target::create([
                'fiscal_year_id' => $fiscalYearId,
                'opb_type' => $opbType,
                'status' => 'draft',
            ]);

            $target->outcomeAreas()->attach($source->outcomeAreas()->pluck('outcome_areas.id'));
            $target->programs()->attach($source->programs()->pluck('programs.id'));

            return $target;
        });
    }
}
