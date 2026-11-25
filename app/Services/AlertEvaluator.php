<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Checks one ISO week of regional case totals against the alert rules.
 *
 * Fixed rules fire when cases reach a set number. Statistical rules fire when cases rise
 * above the mean plus N standard deviations of the same region's previous weeks.
 * Each condition, region and week can only ever produce one alert.
 */
class AlertEvaluator
{
    public function __construct(private readonly AlertNotifier $notifier)
    {
    }

    /**
     * @return Collection<int, Alert> the alerts created for this week
     */
    public function evaluate(CarbonImmutable $week, bool $notify = true): Collection
    {
        $created = collect();
        $rulesByCondition = AlertRule::where('active', true)->get()->groupBy('condition_id');

        foreach ($rulesByCondition as $conditionId => $rules) {
            // Critical rules are checked first so the most serious match wins.
            $rules = $rules->sortBy(fn (AlertRule $r) => $r->severity === 'critical' ? 0 : 1);
            $maxBaseline = (int) $rules->max('baseline_weeks');
            $totals = $this->regionTotals($conditionId, $week->subWeeks($maxBaseline), $week);

            foreach ($totals as $regionId => $weeks) {
                $observed = $weeks[$week->toDateString()] ?? null;
                if ($observed === null) {
                    continue; // nothing reported for this region this week
                }

                foreach ($rules as $rule) {
                    $result = $this->check($rule, $observed, $weeks, $week);
                    if ($result === null) {
                        continue;
                    }

                    $alert = Alert::firstOrCreate(
                        ['condition_id' => $conditionId, 'region_id' => $regionId, 'week_start' => $week->toDateString()],
                        [
                            'alert_rule_id' => $rule->id,
                            'observed' => $observed,
                            'expected' => $result['expected'],
                            'limit_value' => $result['limit'],
                            'severity' => $rule->severity,
                            'status' => 'open',
                        ],
                    );

                    if ($alert->wasRecentlyCreated) {
                        $created->push($alert);
                    }
                    break;
                }
            }
        }

        foreach ($created as $alert) {
            $this->notifier->handle($alert, $notify);
        }

        return $created;
    }

    /**
     * @param  array<string, int>  $weeks  region totals keyed by week start date
     * @return array{expected: ?float, limit: float}|null
     */
    public function check(AlertRule $rule, int $observed, array $weeks, CarbonImmutable $week): ?array
    {
        if ($observed < $rule->min_cases) {
            return null;
        }

        if ($rule->rule_type === AlertRule::FIXED) {
            return $observed >= $rule->threshold ? ['expected' => null, 'limit' => (float) $rule->threshold] : null;
        }

        $baseline = [];
        for ($i = 1; $i <= $rule->baseline_weeks; $i++) {
            $key = $week->subWeeks($i)->toDateString();
            if (array_key_exists($key, $weeks)) {
                $baseline[] = $weeks[$key];
            }
        }

        if (count($baseline) < 4) {
            return null; // not enough history to judge what is unusual
        }

        $mean = array_sum($baseline) / count($baseline);
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $baseline)) / (count($baseline) - 1);

        // Case counts vary at least as much as a Poisson count (variance = mean). Using that as
        // a floor stops a few unusually steady weeks from producing a limit that is too tight.
        $sd = max(sqrt($variance), sqrt($mean));
        $limit = $mean + $rule->threshold * $sd;

        return $observed > $limit ? ['expected' => round($mean, 2), 'limit' => round($limit, 2)] : null;
    }

    /**
     * @return array<int, array<string, int>> region id => [week start => cases]
     */
    private function regionTotals(int $conditionId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('weekly_case_reports as r')
            ->join('facilities as fa', 'fa.id', '=', 'r.facility_id')
            ->where('r.condition_id', $conditionId)
            ->whereBetween('r.week_start', [$from->toDateString(), $to->toDateString()])
            ->groupBy('fa.region_id', 'r.week_start')
            ->selectRaw('fa.region_id, r.week_start, SUM(r.cases) AS cases')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->region_id][substr($row->week_start, 0, 10)] = (int) $row->cases;
        }

        return $totals;
    }
}
