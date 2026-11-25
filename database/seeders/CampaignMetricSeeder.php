<?php

namespace Database\Seeders;

use App\Models\CampaignMetric;
use Illuminate\Database\Seeder;

class CampaignMetricSeeder extends Seeder
{
    public function run(): void
    {
        $handle = fopen(database_path('data/campaign_metrics_export.csv'), 'r');
        $header = fgetcsv($handle, escape: '');

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $record = array_combine($header, $row);

            CampaignMetric::updateOrCreate(
                ['metric_date' => $record['date'], 'platform' => $record['platform']],
                [
                    'impressions' => (int) $record['impressions'],
                    'reach' => (int) $record['reach'],
                    'engagements' => (int) $record['engagements'],
                    'link_clicks' => (int) $record['link_clicks'],
                    'shares' => (int) $record['shares'],
                ],
            );
        }

        fclose($handle);
    }
}
