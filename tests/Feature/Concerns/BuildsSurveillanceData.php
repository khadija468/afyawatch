<?php

namespace Tests\Feature\Concerns;

use App\Models\Condition;
use App\Models\Facility;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CampaignMetricSeeder;
use Database\Seeders\ConditionSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Real regions, conditions, rules and facilities, but no generated case data:
 * each test adds exactly the weekly reports it needs.
 */
trait BuildsSurveillanceData
{
    protected function seedReferenceData(): void
    {
        $this->seed([RegionSeeder::class, ConditionSeeder::class, FacilitySeeder::class, CampaignMetricSeeder::class]);
    }

    protected function region(string $name): Region
    {
        return Region::where('name', $name)->firstOrFail();
    }

    protected function condition(string $icd10): Condition
    {
        return Condition::where('icd10_code', $icd10)->firstOrFail();
    }

    /** Adds one facility's count for one condition and week. */
    protected function report(Facility $facility, string $icd10, string $weekStart, int $cases, int $deaths = 0): void
    {
        $week = CarbonImmutable::parse($weekStart);

        DB::table('weekly_case_reports')->insert([
            'facility_id' => $facility->id,
            'condition_id' => $this->condition($icd10)->id,
            'week_start' => $week->toDateString(),
            'iso_year' => $week->isoWeekYear,
            'iso_week' => $week->isoWeek,
            'cases' => $cases,
            'deaths' => $deaths,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Puts the whole count on the region's hospital. */
    protected function regionReport(string $region, string $icd10, string $weekStart, int $cases, int $deaths = 0): void
    {
        $hospital = $this->region($region)->facilities()->where('level', 'hospital')->firstOrFail();
        $this->report($hospital, $icd10, $weekStart, $cases, $deaths);
    }

    protected function manager(array $attributes = []): User
    {
        return User::factory()->manager()->create($attributes);
    }

    protected function clinicianIn(string $region, array $attributes = []): User
    {
        return User::factory()->clinician($this->region($region)->id)->create($attributes);
    }
}
