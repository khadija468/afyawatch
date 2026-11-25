<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\CampaignMetric;
use App\Models\Condition;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use App\Support\DashboardFilters;
use App\Support\SmallNumbers;
use App\Support\Week;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds every figure the dashboard shows. All queries return aggregates; nothing here
 * reads or returns a single patient's data.
 */
class SurveillanceStats
{
    public function dashboard(DashboardFilters $f, User $user): array
    {
        $trend = $this->trend($f);
        $coverage = $this->coverage($f);

        return [
            'filters' => $f->toArray(),
            'kpis' => $this->kpis($f, $trend, $coverage, $user),
            'trend' => $trend,
            'control' => $this->controlChart($f, $trend),
            'regions' => $this->regionBreakdown($f, $user),
            'facilities' => $f->region ? $this->facilityBreakdown($f) : [],
            'coverage' => $coverage,
            'conditions' => $this->conditionOverview($f),
            'campaign' => $user->isManager() ? $this->campaign() : null,
            'alerts' => $this->alerts($f, $user),
        ];
    }

    /** Weekly cases for the selected condition, with an 8-week rolling mean. */
    public function trend(DashboardFilters $f): array
    {
        $lookbackStart = $f->from->subWeeks(7);
        $rows = $this->caseQuery($f)
            ->whereBetween('r.week_start', [$lookbackStart->toDateString(), $f->to->toDateString()])
            ->groupBy('r.week_start')
            ->selectRaw('r.week_start, SUM(r.cases) AS cases, SUM(r.deaths) AS deaths, COUNT(DISTINCT r.facility_id) AS reporting')
            ->get()
            ->keyBy(fn ($row) => substr($row->week_start, 0, 10));

        $expected = $this->facilityCount($f);
        $window = [];
        $series = [];

        foreach (Week::range($lookbackStart, $f->to) as $week) {
            $row = $rows->get($week->toDateString());
            $cases = $row ? (int) $row->cases : null;

            $window[] = $cases;
            if (count($window) > 8) {
                array_shift($window);
            }

            if ($week->lt($f->from)) {
                continue;
            }

            $known = array_filter($window, fn ($v) => $v !== null);
            $series[] = [
                'week' => $week->toDateString(),
                'label' => Week::label($week),
                'cases' => $cases,
                'deaths' => $row ? (int) $row->deaths : null,
                'reporting' => $row ? (int) $row->reporting : 0,
                'expected' => $expected,
                'rolling_mean' => $known ? round(array_sum($known) / count($known), 1) : null,
                'small' => SmallNumbers::isSmall($cases),
            ];
        }

        return $series;
    }

    public function kpis(DashboardFilters $f, array $trend, array $coverage, User $user): array
    {
        $cases = array_sum(array_map(fn ($w) => $w['cases'] ?? 0, $trend));
        $deaths = array_sum(array_map(fn ($w) => $w['deaths'] ?? 0, $trend));
        $received = array_sum(array_column($trend, 'reporting'));
        $expected = $this->facilityCount($f) * count($trend);
        $population = $this->population($f);

        return [
            'cases' => $cases,
            'cases_small' => SmallNumbers::isSmall($cases),
            'deaths' => $deaths,
            'population' => $population,
            'incidence_per_100k' => $population ? round($cases / $population * 100000, 2) : null,
            'cfr_pct' => $cases >= SmallNumbers::MIN_CASES_FOR_RATE ? round($deaths / $cases * 100, 1) : null,
            'completeness' => [
                'received' => $received,
                'expected' => $expected,
                'pct' => $expected ? round($received / $expected * 100, 1) : null,
                'target' => config('afyawatch.completeness_target'),
            ],
            'mr1_coverage' => $coverage['totals']['MR1'],
            'open_alerts' => $this->alertQuery($f, $user)->where('status', 'open')->count(),
        ];
    }

    /**
     * c-chart: centre line and 3-sigma limits from a baseline of up to 26 weeks before the
     * selected period. Falls back to the selected weeks when there is too little history.
     */
    public function controlChart(DashboardFilters $f, array $trend): array
    {
        $baselineStart = $f->from->subWeeks(26);
        $baselineRows = $this->caseQuery($f)
            ->whereBetween('r.week_start', [$baselineStart->toDateString(), $f->from->subWeek()->toDateString()])
            ->groupBy('r.week_start')
            ->selectRaw('r.week_start, SUM(r.cases) AS cases')
            ->orderBy('r.week_start')
            ->get();

        $fromRange = $baselineRows->count() < 8;
        $values = $fromRange
            ? array_values(array_filter(array_column($trend, 'cases'), fn ($v) => $v !== null))
            : $baselineRows->pluck('cases')->map(fn ($v) => (int) $v)->all();

        $centre = $values ? array_sum($values) / count($values) : 0.0;
        $ucl = $centre + 3 * sqrt($centre);
        $lcl = max(0.0, $centre - 3 * sqrt($centre));

        $points = [];
        $run = 0;
        foreach ($trend as $i => $week) {
            $above = $week['cases'] !== null && $week['cases'] > $centre;
            $run = $above ? $run + 1 : 0;
            $points[] = [
                'week' => $week['week'],
                'label' => $week['label'],
                'cases' => $week['cases'],
                'above_ucl' => $week['cases'] !== null && $week['cases'] > $ucl,
                'run_length' => $run,
            ];
        }

        // Mark every point that belongs to a run of 8 or more weeks above the centre line.
        for ($i = count($points) - 1; $i >= 0; $i--) {
            if ($points[$i]['run_length'] >= 8) {
                for ($j = $i; $j > $i - $points[$i]['run_length']; $j--) {
                    $points[$j]['in_run'] = true;
                }
            }
        }

        $baselineWeeks = $fromRange ? array_column($trend, 'week') : $baselineRows->pluck('week_start')->all();

        return [
            'centre' => round($centre, 2),
            'ucl' => round($ucl, 2),
            'lcl' => round($lcl, 2),
            'baseline' => [
                'from_label' => $baselineWeeks ? Week::label(reset($baselineWeeks)) : null,
                'to_label' => $baselineWeeks ? Week::label(end($baselineWeeks)) : null,
                'weeks' => count($values),
                'from_selected_range' => $fromRange,
            ],
            'points' => array_map(fn ($p) => $p + ['in_run' => false], $points),
        ];
    }

    /**
     * Cases and incidence per region for the tile map and the comparison chart.
     * Regions outside a clinician's view are returned without figures.
     */
    public function regionBreakdown(DashboardFilters $f, User $user): array
    {
        $rows = DB::table('weekly_case_reports as r')
            ->join('facilities as fa', 'fa.id', '=', 'r.facility_id')
            ->where('r.condition_id', $f->condition->id)
            ->whereBetween('r.week_start', [$f->from->toDateString(), $f->to->toDateString()])
            ->when($user->scopedRegionId(), fn ($q, $regionId) => $q->where('fa.region_id', $regionId))
            ->groupBy('fa.region_id')
            ->selectRaw('fa.region_id, SUM(r.cases) AS cases, SUM(r.deaths) AS deaths, COUNT(*) AS reports')
            ->get()
            ->keyBy('region_id');

        $facilitiesPerRegion = Facility::query()->selectRaw('region_id, COUNT(*) AS n')->groupBy('region_id')->pluck('n', 'region_id');
        $weeks = $f->weekCount();

        return Region::orderBy('name')->get()->map(function (Region $region) use ($rows, $user, $facilitiesPerRegion, $weeks) {
            $inView = $user->canSeeRegion($region->id);
            $row = $rows->get($region->id);
            $cases = $inView ? (int) ($row->cases ?? 0) : null;

            return [
                'id' => $region->id,
                'name' => $region->name,
                'iso_code' => $region->iso_code,
                'tile_col' => $region->tile_col,
                'tile_row' => $region->tile_row,
                'population' => $region->population_2022,
                'in_view' => $inView,
                'cases' => $cases,
                'deaths' => $inView ? (int) ($row->deaths ?? 0) : null,
                'incidence_per_100k' => $inView ? round($cases / $region->population_2022 * 100000, 2) : null,
                'reports_received' => $inView ? (int) ($row->reports ?? 0) : null,
                'reports_expected' => (int) ($facilitiesPerRegion[$region->id] ?? 0) * $weeks,
                'small' => SmallNumbers::isSmall($cases),
            ];
        })->all();
    }

    /** Cases per facility inside the selected region (drill-down view). */
    public function facilityBreakdown(DashboardFilters $f): array
    {
        $rows = DB::table('weekly_case_reports as r')
            ->where('r.condition_id', $f->condition->id)
            ->whereBetween('r.week_start', [$f->from->toDateString(), $f->to->toDateString()])
            ->whereIn('r.facility_id', $f->region->facilities()->pluck('id'))
            ->groupBy('r.facility_id')
            ->selectRaw('r.facility_id, SUM(r.cases) AS cases, SUM(r.deaths) AS deaths, COUNT(*) AS weeks_reported')
            ->get()
            ->keyBy('facility_id');

        return $f->region->facilities()->orderBy('code')->get()->map(fn (Facility $facility) => [
            'id' => $facility->id,
            'name' => $facility->name,
            'code' => $facility->code,
            'level' => $facility->level,
            'cases' => (int) ($rows->get($facility->id)->cases ?? 0),
            'deaths' => (int) ($rows->get($facility->id)->deaths ?? 0),
            'weeks_reported' => (int) ($rows->get($facility->id)->weeks_reported ?? 0),
            'weeks_expected' => $f->weekCount(),
            'small' => SmallNumbers::isSmall((int) ($rows->get($facility->id)->cases ?? 0)),
        ])->all();
    }

    /**
     * Monthly MR1 and Penta3 coverage for the last 12 reported months. Facility catchment
     * populations are unknown, so a facility filter falls back to its region.
     */
    public function coverage(DashboardFilters $f): array
    {
        $region = $f->region ?? $f->facility?->region;
        $population = $region?->population_2022 ?? (int) Region::sum('population_2022');
        $monthlyTarget = $population * config('afyawatch.surviving_infant_share') / 12;

        $rows = DB::table('immunization_reports as i')
            ->join('facilities as fa', 'fa.id', '=', 'i.facility_id')
            ->when($region, fn ($q) => $q->where('fa.region_id', $region->id))
            ->groupBy('i.month', 'i.vaccine')
            ->selectRaw('i.month, i.vaccine, SUM(i.doses) AS doses')
            ->orderBy('i.month')
            ->get();

        $months = $rows->groupBy(fn ($r) => substr($r->month, 0, 7))->sortKeys()->take(-12);
        $series = [];
        $totals = ['MR1' => 0, 'Penta3' => 0];

        foreach ($months as $month => $vaccines) {
            $entry = ['month' => $month, 'target' => (int) round($monthlyTarget)];
            foreach (['MR1', 'Penta3'] as $vaccine) {
                $doses = (int) ($vaccines->firstWhere('vaccine', $vaccine)->doses ?? 0);
                $totals[$vaccine] += $doses;
                $entry[$vaccine] = [
                    'doses' => $doses,
                    'pct' => $monthlyTarget ? round($doses / $monthlyTarget * 100, 1) : null,
                ];
            }
            $series[] = $entry;
        }

        $periodTarget = $monthlyTarget * count($series);
        $totalOf = fn (string $vaccine) => [
            'doses' => $totals[$vaccine],
            'target' => (int) round($periodTarget),
            'pct' => $periodTarget ? round($totals[$vaccine] / $periodTarget * 100, 1) : null,
        ];

        return [
            'scope_label' => $region?->name ?? 'Tanzania Mainland',
            'facility_fallback' => $f->facility !== null,
            'target_pct' => config('afyawatch.coverage_target'),
            'months' => $series,
            'totals' => ['MR1' => $totalOf('MR1'), 'Penta3' => $totalOf('Penta3')],
        ];
    }

    /** Last week against the week before, for every condition, in the current scope. */
    public function conditionOverview(DashboardFilters $f): array
    {
        $previous = $f->to->subWeek();
        $rows = $this->scoped(DB::table('weekly_case_reports as r'), $f)
            ->whereIn('r.week_start', [$previous->toDateString(), $f->to->toDateString()])
            ->groupBy('r.condition_id', 'r.week_start')
            ->selectRaw('r.condition_id, r.week_start, SUM(r.cases) AS cases')
            ->get();

        return Condition::orderBy('name')->get()->map(function (Condition $condition) use ($rows, $f, $previous) {
            $find = fn (CarbonImmutable $week) => $rows->first(
                fn ($r) => (int) $r->condition_id === $condition->id && substr($r->week_start, 0, 10) === $week->toDateString()
            );
            $thisWeek = $find($f->to)?->cases;
            $lastWeek = $find($previous)?->cases;

            return [
                'id' => $condition->id,
                'name' => $condition->name,
                'icd10' => $condition->icd10_code,
                'snomed' => $condition->snomed_code,
                'case_basis' => $condition->case_basis,
                'this_week' => $thisWeek === null ? null : (int) $thisWeek,
                'last_week' => $lastWeek === null ? null : (int) $lastWeek,
                'this_week_small' => SmallNumbers::isSmall($thisWeek === null ? null : (int) $thisWeek),
            ];
        })->all();
    }

    /** Weekly campaign figures per platform (national campaign, managers only). */
    public function campaign(): array
    {
        $metrics = CampaignMetric::orderBy('metric_date')->get();
        if ($metrics->isEmpty()) {
            return ['weeks' => [], 'platforms' => [], 'totals' => null];
        }

        $platforms = $metrics->pluck('platform')->unique()->values()->all();
        $weeks = $metrics->groupBy(fn ($m) => Week::start($m->metric_date)->toDateString())
            ->map(function ($items, $week) use ($platforms) {
                $byPlatform = [];
                foreach ($platforms as $platform) {
                    $rows = $items->where('platform', $platform);
                    $byPlatform[$platform] = [
                        'reach' => (int) $rows->sum('reach'),
                        'engagements' => (int) $rows->sum('engagements'),
                    ];
                }

                return ['week' => $week, 'label' => Week::label($week), 'platforms' => $byPlatform];
            })->values()->all();

        $reach = (int) $metrics->sum('reach');
        $engagements = (int) $metrics->sum('engagements');

        return [
            'name' => 'Measles-rubella catch-up campaign',
            'period' => [
                'from' => $metrics->first()->metric_date,
                'to' => $metrics->last()->metric_date,
            ],
            'platforms' => $platforms,
            'weeks' => $weeks,
            'totals' => [
                'impressions' => (int) $metrics->sum('impressions'),
                'reach' => $reach,
                'engagements' => $engagements,
                'engagement_rate_pct' => $reach ? round($engagements / $reach * 100, 2) : null,
                'link_clicks' => (int) $metrics->sum('link_clicks'),
                'shares' => (int) $metrics->sum('shares'),
            ],
        ];
    }

    public function alerts(DashboardFilters $f, User $user): array
    {
        return $this->alertQuery($f, $user)
            ->with(['condition', 'region', 'acknowledgedBy'])
            ->whereBetween('week_start', [$f->to->subWeeks(7)->toDateString(), $f->to->toDateString()])
            ->orderByDesc('week_start')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 ELSE 1 END")
            ->limit(50)
            ->get()
            ->map(fn (Alert $a) => [
                'id' => $a->id,
                'condition' => $a->condition->name,
                'icd10' => $a->condition->icd10_code,
                'region' => $a->region->name,
                'week' => $a->week_start,
                'week_label' => Week::label($a->week_start),
                'observed' => $a->observed,
                'expected' => $a->expected,
                'limit' => $a->limit_value,
                'rule_type' => $a->rule?->rule_type,
                'severity' => $a->severity,
                'status' => $a->status,
                'acknowledged_by' => $a->acknowledgedBy?->name,
                'acknowledged_at' => $a->acknowledged_at?->toIso8601String(),
                'emailed_at' => $a->emailed_at?->toIso8601String(),
                'email_skipped_reason' => $a->email_skipped_reason,
            ])->all();
    }

    /** Aggregated rows for the CSV export: one row per region and week. */
    public function exportRows(DashboardFilters $f): array
    {
        $rows = $this->scoped(DB::table('weekly_case_reports as r'), $f)
            ->join('regions as rg', 'rg.id', '=', 'fa.region_id')
            ->where('r.condition_id', $f->condition->id)
            ->whereBetween('r.week_start', [$f->from->toDateString(), $f->to->toDateString()])
            ->groupBy('rg.id', 'rg.name', 'rg.iso_code', 'rg.population_2022', 'r.week_start')
            ->selectRaw('rg.name, rg.iso_code, rg.population_2022, r.week_start, SUM(r.cases) AS cases, SUM(r.deaths) AS deaths, COUNT(*) AS reports')
            ->orderBy('rg.name')
            ->orderBy('r.week_start')
            ->get();

        $facilities = Facility::query()->selectRaw('region_id, COUNT(*) AS n')->groupBy('region_id')->pluck('n', 'region_id');
        $regionIds = Region::pluck('id', 'iso_code');

        return $rows->map(fn ($r) => [
            'week' => Week::label($r->week_start),
            'week_start' => substr($r->week_start, 0, 10),
            'region' => $r->name,
            'region_code' => $r->iso_code,
            'cases' => (int) $r->cases,
            'deaths' => (int) $r->deaths,
            'population' => (int) $r->population_2022,
            'reports_received' => (int) $r->reports,
            'reports_expected' => $f->facility ? 1 : (int) ($facilities[$regionIds[$r->iso_code]] ?? 0),
        ])->all();
    }

    /** National figures for the public summary page; small counts are not shown exactly. */
    public function publicSummary(): array
    {
        $week = DashboardFilters::latestWeek();
        $rows = DB::table('weekly_case_reports')
            ->where('week_start', $week->toDateString())
            ->groupBy('condition_id')
            ->selectRaw('condition_id, SUM(cases) AS cases')
            ->pluck('cases', 'condition_id');
        $reporting = DB::table('weekly_case_reports')->where('week_start', $week->toDateString())->distinct()->count('facility_id');

        return [
            'week' => $week,
            'week_label' => Week::label($week),
            'week_end' => $week->addDays(6),
            'completeness_pct' => round($reporting / max(1, Facility::count()) * 100),
            'conditions' => Condition::orderBy('name')->get()->map(fn (Condition $c) => [
                'name' => $c->name,
                'icd10' => $c->icd10_code,
                'display' => SmallNumbers::isSmall((int) ($rows[$c->id] ?? 0))
                    ? 'fewer than '.SmallNumbers::THRESHOLD
                    : number_format((int) ($rows[$c->id] ?? 0)),
            ])->all(),
        ];
    }

    private function caseQuery(DashboardFilters $f): Builder
    {
        return $this->scoped(DB::table('weekly_case_reports as r'), $f)
            ->where('r.condition_id', $f->condition->id);
    }

    private function scoped(Builder $query, DashboardFilters $f): Builder
    {
        return $query
            ->join('facilities as fa', 'fa.id', '=', 'r.facility_id')
            ->when($f->region, fn ($q, $region) => $q->where('fa.region_id', $region->id))
            ->when($f->facility, fn ($q, $facility) => $q->where('fa.id', $facility->id));
    }

    private function alertQuery(DashboardFilters $f, User $user)
    {
        return Alert::query()
            ->when($user->scopedRegionId() ?? $f->region?->id, fn ($q, $regionId) => $q->where('region_id', $regionId));
    }

    private function facilityCount(DashboardFilters $f): int
    {
        if ($f->facility) {
            return 1;
        }

        return $f->region ? $f->region->facilities()->count() : Facility::count();
    }

    private function population(DashboardFilters $f): ?int
    {
        if ($f->facility) {
            return null; // facility catchment population is not known
        }

        return $f->region?->population_2022 ?? (int) Region::sum('population_2022');
    }
}
