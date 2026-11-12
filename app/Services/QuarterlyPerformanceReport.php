<?php

namespace App\Services;

use App\Models\Accomplishment;
use Illuminate\Support\Collection;

class QuarterlyPerformanceReport
{
    public function rows(mixed $fiscalYearId, mixed $opbType, mixed $officeId = null): array
    {
        return $this->buildRows($this->counts($fiscalYearId, $opbType, $officeId));
    }

    public function tables(mixed $fiscalYearId, mixed $opbType, mixed $officeId = null): array
    {
        $counts = $this->counts($fiscalYearId, $opbType, $officeId);

        return [
            'rows' => $this->buildRows($counts),
            'cumulativeRows' => $this->buildRows($counts, cumulative: true),
        ];
    }

    private function counts(mixed $fiscalYearId, mixed $opbType, mixed $officeId): Collection
    {
        $validYear = $fiscalYearId === null || $fiscalYearId === '' || (is_scalar($fiscalYearId) && filter_var($fiscalYearId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false);
        $validOpb = $opbType === null || $opbType === '' || in_array($opbType, ['current', 'continuing'], true);
        $validOffice = $officeId === null || $officeId === '' || (is_scalar($officeId) && filter_var($officeId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false);

        $counts = collect();

        if ($validYear && $validOpb && $validOffice) {
            $counts = Accomplishment::query()
                ->join('performance_indicators', 'performance_indicators.id', '=', 'accomplishments.performance_indicator_id')
                ->join('targets', 'targets.id', '=', 'performance_indicators.target_id')
                ->when(filled($fiscalYearId), fn ($query) => $query->where('targets.fiscal_year_id', (int) $fiscalYearId))
                ->when(filled($opbType), fn ($query) => $query->where('targets.opb_type', $opbType))
                ->when(filled($officeId), fn ($query) => $query->where('performance_indicators.office_id', (int) $officeId))
                ->whereBetween('accomplishments.target_quarter', [1, 4])
                ->selectRaw("accomplishments.target_quarter, COUNT(*) AS target_count,
                    SUM(CASE WHEN accomplishments.tag = 'met' THEN 1 ELSE 0 END) AS met,
                    SUM(CASE WHEN accomplishments.tag = 'unmet' THEN 1 ELSE 0 END) AS unmet,
                    SUM(CASE WHEN accomplishments.tag = 'cont' THEN 1 ELSE 0 END) AS continuing,
                    SUM(CASE WHEN accomplishments.tag = 'na' THEN 1 ELSE 0 END) AS na,
                    SUM(CASE WHEN accomplishments.tag = 'exceeded' THEN 1 ELSE 0 END) AS exceeded,
                    SUM(CASE WHEN accomplishments.tag = 'deferred' THEN 1 ELSE 0 END) AS deferred")
                ->groupBy('accomplishments.target_quarter')
                ->get()
                ->keyBy('target_quarter');
        }

        return $counts;
    }

    private function buildRows(Collection $counts, bool $cumulative = false): array
    {
        $rows = [];
        $totals = array_fill_keys(['target', 'continuing', 'deferred', 'na', 'met', 'unmet', 'exceeded'], 0);

        for ($quarter = 1; $quarter <= 4; $quarter++) {
            $count = $counts->get($quarter);
            $quarterCounts = [
                'target' => (int) ($count?->target_count ?? 0),
                'continuing' => (int) ($count?->continuing ?? 0),
                'deferred' => (int) ($count?->deferred ?? 0),
                'na' => (int) ($count?->na ?? 0),
                'met' => (int) ($count?->met ?? 0),
                'unmet' => (int) ($count?->unmet ?? 0),
                'exceeded' => (int) ($count?->exceeded ?? 0),
            ];

            foreach ($quarterCounts as $key => $value) {
                $totals[$key] = $cumulative ? $totals[$key] + $value : $value;
            }

            $denominator = $totals['target'] - $totals['continuing'] - $totals['deferred'] - $totals['na'];
            $factor = $cumulative ? $quarter / 4 : 1;

            $rows[] = [
                'quarter' => $quarter,
                'compliance' => $denominator > 0 ? round(($totals['met'] + $totals['exceeded']) / $denominator * 100 * $factor, 2) : null,
                'target' => $totals['target'],
                'met' => $totals['met'],
                'unmet' => $totals['unmet'],
                'deferred' => $totals['deferred'],
                'na' => $totals['na'],
                'exceeded' => $totals['exceeded'],
            ];
        }

        return $rows;
    }
}
