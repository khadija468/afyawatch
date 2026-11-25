<?php

namespace Database\Seeders;

use App\Services\AlertEvaluator;
use App\Support\DashboardFilters;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            ConditionSeeder::class,
            FacilitySeeder::class,
            SurveillanceDataSeeder::class,
            CampaignMetricSeeder::class,
            DemoUserSeeder::class,
        ]);

        // Build the alert history for the seven weeks before the latest one, without email.
        // The latest week is left for `php artisan alerts:evaluate`, so a live run sends real alerts.
        $latest = DashboardFilters::latestWeek();
        $evaluator = app(AlertEvaluator::class);

        for ($i = 7; $i >= 1; $i--) {
            $evaluator->evaluate($latest->subWeeks($i), notify: false);
        }
    }
}
