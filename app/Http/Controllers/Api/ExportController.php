<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Models\AuditLog;
use App\Services\SurveillanceStats;
use App\Support\SmallNumbers;
use App\Support\Week;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of aggregated counts (region x week). Counts from 1 to 4 are written as "<5"
 * because a downloaded file can travel well beyond the people allowed to see the dashboard.
 */
class ExportController extends Controller
{
    public function __invoke(DashboardRequest $request, SurveillanceStats $stats): StreamedResponse
    {
        $filters = $request->filters();
        $rows = $stats->exportRows($filters);

        AuditLog::record($request, 'export_csv', $filters->toArray() + ['rows' => count($rows)]);

        $filename = sprintf(
            'afyawatch_%s_%s_%s_to_%s.csv',
            Str::slug($filters->condition->name, '_'),
            Str::slug($filters->scopeLabel(), '_'),
            Week::label($filters->from),
            Week::label($filters->to),
        );

        return response()->streamDownload(function () use ($rows, $filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'iso_week', 'week_start', 'region', 'region_code', 'condition', 'icd10_code',
                'cases', 'deaths', 'population_2022', 'incidence_per_100k', 'reports_received', 'reports_expected',
            ], escape: '');

            foreach ($rows as $row) {
                $suppressed = SmallNumbers::isSmall($row['cases']);
                fputcsv($out, [
                    $row['week'],
                    $row['week_start'],
                    $row['region'],
                    $row['region_code'],
                    $filters->condition->name,
                    $filters->condition->icd10_code,
                    SmallNumbers::forExport($row['cases']),
                    $suppressed ? '' : (string) $row['deaths'],
                    $row['population'],
                    $suppressed ? '' : number_format($row['cases'] / $row['population'] * 100000, 2, '.', ''),
                    $row['reports_received'],
                    $row['reports_expected'],
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
