<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Models\AuditLog;
use App\Services\SurveillanceStats;
use App\Support\Week;
use Illuminate\Http\JsonResponse;

/**
 * Serves regional incidence as HL7 FHIR R4 MeasureReport resources (summary type).
 * Only aggregates are exposed; there are no Patient or Condition resources by design.
 */
class FhirController extends Controller
{
    private const POPULATION_SYSTEM = 'http://terminology.hl7.org/CodeSystem/measure-population';

    public function measureReport(DashboardRequest $request, SurveillanceStats $stats): JsonResponse
    {
        $filters = $request->filters();
        $condition = $filters->condition;
        $base = rtrim(config('afyawatch.fhir_base'), '/');
        $periodEnd = $filters->to->addDays(6)->toDateString();

        $regions = collect($stats->regionBreakdown($filters, $request->user()))
            ->filter(fn ($r) => $r['in_view'] && (! $filters->region || $r['id'] === $filters->region->id))
            ->values();

        $entries = $regions->map(function (array $region) use ($condition, $base, $filters, $periodEnd) {
            $id = sprintf('%s-%s-%s-%s', str_replace('.', '', $condition->icd10_code), $region['iso_code'], Week::label($filters->from), Week::label($filters->to));

            return [
                'fullUrl' => "{$base}/MeasureReport/{$id}",
                'resource' => [
                    'resourceType' => 'MeasureReport',
                    'id' => $id,
                    'status' => 'complete',
                    'type' => 'summary',
                    'measure' => "{$base}/Measure/incidence-".str_replace('.', '', $condition->icd10_code),
                    'subject' => [
                        'identifier' => ['system' => 'urn:iso:std:iso:3166:-2', 'value' => $region['iso_code']],
                        'display' => $region['name'].' Region, Tanzania',
                    ],
                    'date' => now()->toIso8601String(),
                    'period' => ['start' => $filters->from->toDateString(), 'end' => $periodEnd],
                    'improvementNotation' => ['coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/measure-improvement-notation',
                        'code' => 'decrease',
                    ]]],
                    'group' => [[
                        'code' => ['coding' => array_values(array_filter([
                            ['system' => 'http://hl7.org/fhir/sid/icd-10', 'code' => $condition->icd10_code, 'display' => $condition->name],
                            $condition->snomed_code ? ['system' => 'http://snomed.info/sct', 'code' => $condition->snomed_code, 'display' => $condition->name] : null,
                        ]))],
                        'population' => [
                            ['code' => ['coding' => [['system' => self::POPULATION_SYSTEM, 'code' => 'numerator']]], 'count' => $region['cases']],
                            ['code' => ['coding' => [['system' => self::POPULATION_SYSTEM, 'code' => 'denominator']]], 'count' => $region['population']],
                        ],
                        'measureScore' => ['value' => $region['incidence_per_100k'], 'unit' => 'cases per 100 000 population'],
                    ]],
                ],
            ];
        });

        AuditLog::record($request, 'fhir_measure_report', $filters->toArray());

        return response()->json([
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'timestamp' => now()->toIso8601String(),
            'total' => $entries->count(),
            'entry' => $entries->all(),
        ], 200, ['Content-Type' => 'application/fhir+json'], JSON_UNESCAPED_SLASHES);
    }
}
