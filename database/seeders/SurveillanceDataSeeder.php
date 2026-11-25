<?php

namespace Database\Seeders;

use App\Models\Condition;
use App\Models\Facility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generates synthetic weekly surveillance and monthly immunization reports.
 *
 * Counts are drawn from Poisson distributions whose means depend on facility level,
 * region and season. A fixed seed keeps the data identical on every install.
 */
class SurveillanceDataSeeder extends Seeder
{
    public const FIRST_WEEK = '2025-11-24';
    public const WEEKS = 52;

    /** Mean weekly cases per facility, by facility level. */
    private const BASE = [
        'B54' => ['hospital' => 30, 'health_centre' => 16, 'dispensary' => 7],
        'A01.0' => ['hospital' => 3, 'health_centre' => 1.5, 'dispensary' => 0.6],
        'A97' => ['hospital' => 1.5, 'health_centre' => 0.6, 'dispensary' => 0.2],
        'G00' => ['hospital' => 0.25, 'health_centre' => 0.08, 'dispensary' => 0.02],
        'B05' => ['hospital' => 0.12, 'health_centre' => 0.05, 'dispensary' => 0.02],
        'A00' => ['hospital' => 0, 'health_centre' => 0, 'dispensary' => 0],
    ];

    /** Relative malaria burden by region (lake zone and south-east high, highlands low). */
    private const MALARIA = [
        'Kagera' => 1.9, 'Geita' => 1.8, 'Kigoma' => 1.7, 'Mtwara' => 1.6, 'Lindi' => 1.6,
        'Ruvuma' => 1.4, 'Katavi' => 1.4, 'Mwanza' => 1.3, 'Tabora' => 1.3, 'Mara' => 1.2,
        'Pwani' => 1.0, 'Morogoro' => 1.0, 'Shinyanga' => 1.0, 'Tanga' => 0.9, 'Simiyu' => 0.9,
        'Rukwa' => 0.8, 'Songwe' => 0.6, 'Dar es Salaam' => 0.4, 'Singida' => 0.4, 'Mbeya' => 0.4,
        'Dodoma' => 0.3, 'Manyara' => 0.2, 'Kilimanjaro' => 0.15, 'Iringa' => 0.15,
        'Arusha' => 0.1, 'Njombe' => 0.1,
    ];

    private const DENGUE = ['Dar es Salaam' => 1.0, 'Tanga' => 0.5, 'Pwani' => 0.4, 'Morogoro' => 0.2];

    /** Planted outbreaks: region => [ICD-10, [week_start => region total]]. */
    private const OUTBREAKS = [
        ['Kigoma', 'A00', ['2026-10-26' => 2, '2026-11-02' => 7, '2026-11-09' => 13, '2026-11-16' => 9]],
        ['Kagera', 'B05', [
            '2026-10-05' => 3, '2026-10-12' => 5, '2026-10-19' => 8, '2026-10-26' => 12,
            '2026-11-02' => 16, '2026-11-09' => 14, '2026-11-16' => 11,
        ]],
    ];

    /** Case fatality used to draw deaths. */
    private const CFR = ['A00' => 0.02, 'B05' => 0.01, 'B54' => 0.003, 'A01.0' => 0.005, 'A97' => 0.005, 'G00' => 0.1];

    private const REPORTING = ['hospital' => 0.97, 'health_centre' => 0.93, 'dispensary' => 0.88];
    private const WEAK_REPORTING_REGIONS = ['Katavi', 'Rukwa'];

    private const FACILITY_SHARE = ['hospital' => 0.25, 'health_centre' => 0.2, 'dispensary' => 0.35 / 3];

    public function run(): void
    {
        mt_srand(4207);

        DB::table('weekly_case_reports')->delete();
        DB::table('immunization_reports')->delete();

        $facilities = Facility::with('region')->orderBy('id')->get();
        $conditions = Condition::all()->keyBy('icd10_code');
        $outbreaks = $this->outbreakShares($facilities);

        $this->seedWeeklyReports($facilities, $conditions, $outbreaks);
        $this->seedImmunization($facilities);
    }

    private function seedWeeklyReports($facilities, $conditions, array $outbreaks): void
    {
        $typhoidFactor = [];
        $now = now();
        $rows = [];
        $first = CarbonImmutable::parse(self::FIRST_WEEK);

        for ($w = 0; $w < self::WEEKS; $w++) {
            $week = $first->addWeeks($w);
            $weekKey = $week->toDateString();
            $season = 1 + 0.35 * cos(2 * M_PI * ($week->isoWeek - 18) / 52);
            $dengueSeason = ($week->isoWeek >= 16 && $week->isoWeek <= 24) ? 3.0 : 1.0;

            foreach ($facilities as $facility) {
                $region = $facility->region->name;
                $reportingChance = self::REPORTING[$facility->level]
                    * (in_array($region, self::WEAK_REPORTING_REGIONS, true) ? 0.85 : 1.0);

                if ($this->uniform() > $reportingChance) {
                    continue; // report not received this week
                }

                $typhoidFactor[$region] ??= 0.6 + 0.8 * $this->uniform();

                foreach ($conditions as $icd => $condition) {
                    $mean = self::BASE[$icd][$facility->level] * match ($icd) {
                        'B54' => self::MALARIA[$region] * $season,
                        'A01.0' => $typhoidFactor[$region],
                        'A97' => (self::DENGUE[$region] ?? 0.02) * $dengueSeason,
                        default => 1.0,
                    };

                    $cases = $this->poisson($mean)
                        + ($outbreaks[$icd][$facility->id][$weekKey] ?? 0);
                    $deaths = $this->binomial($cases, self::CFR[$icd]);

                    $rows[] = [
                        'facility_id' => $facility->id,
                        'condition_id' => $condition->id,
                        'week_start' => $weekKey,
                        'iso_year' => $week->isoWeekYear,
                        'iso_week' => $week->isoWeek,
                        'cases' => $cases,
                        'deaths' => $deaths,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($rows) >= 500) {
                        DB::table('weekly_case_reports')->insert($rows);
                        $rows = [];
                    }
                }
            }
        }

        if ($rows) {
            DB::table('weekly_case_reports')->insert($rows);
        }
    }

    /**
     * Splits each planted outbreak's weekly regional total across the region's facilities,
     * with the hospital taking the largest share.
     */
    private function outbreakShares($facilities): array
    {
        $shares = [];

        foreach (self::OUTBREAKS as [$regionName, $icd, $weeks]) {
            $regionFacilities = $facilities->filter(fn ($f) => $f->region->name === $regionName)->values();
            $weights = $regionFacilities->map(fn ($f) => self::FACILITY_SHARE[$f->level])->all();
            $totalWeight = array_sum($weights);

            foreach ($weeks as $weekKey => $total) {
                $assigned = 0;
                foreach ($regionFacilities as $i => $facility) {
                    $n = $i === count($regionFacilities) - 1
                        ? $total - $assigned
                        : (int) floor($total * $weights[$i] / $totalWeight);
                    $shares[$icd][$facility->id][$weekKey] = $n;
                    $assigned += $n;
                }
            }
        }

        return $shares;
    }

    private function seedImmunization($facilities): void
    {
        $coverage = [];
        $now = now();
        $rows = [];
        $firstMonth = CarbonImmutable::parse('2025-11-01');

        for ($m = 0; $m < 12; $m++) {
            $month = $firstMonth->addMonths($m);

            foreach ($facilities as $facility) {
                $region = $facility->region;
                $coverage[$region->id] ??= 0.70 + 0.32 * $this->uniform();
                $monthlyTarget = $region->population_2022 * config('afyawatch.surviving_infant_share') / 12;
                $campaignBoost = $month->format('Y-m') === '2026-10' ? 1.1 : 1.0;

                foreach (['MR1' => $coverage[$region->id] * $campaignBoost, 'Penta3' => $coverage[$region->id] + 0.04] as $vaccine => $rate) {
                    $noise = 0.92 + 0.16 * $this->uniform();
                    $rows[] = [
                        'facility_id' => $facility->id,
                        'month' => $month->toDateString(),
                        'vaccine' => $vaccine,
                        'doses' => (int) round($monthlyTarget * $rate * self::FACILITY_SHARE[$facility->level] * $noise),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('immunization_reports')->insert($chunk);
        }
    }

    private function uniform(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        if ($lambda > 30) {
            // Normal approximation for large means.
            $z = sqrt(-2 * log(max($this->uniform(), 1e-12))) * cos(2 * M_PI * $this->uniform());

            return max(0, (int) round($lambda + sqrt($lambda) * $z));
        }

        $limit = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= $this->uniform();
        } while ($p > $limit);

        return $k - 1;
    }

    private function binomial(int $n, float $p): int
    {
        $k = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($this->uniform() < $p) {
                $k++;
            }
        }

        return $k;
    }
}
