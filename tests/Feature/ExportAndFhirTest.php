<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class ExportAndFhirTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->regionReport('Kigoma', 'A00', '2026-11-09', 3, 1);
        $this->regionReport('Kigoma', 'A00', '2026-11-16', 12, 0);
    }

    public function test_csv_export_hides_small_counts_and_is_audited(): void
    {
        $response = $this->actingAs($this->manager())
            ->get('/api/export.csv?condition_id='.$this->condition('A00')->id.'&from=2026-11-09&to=2026-11-16');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $lines = array_map('str_getcsv', array_filter(explode("\n", $response->streamedContent())));
        $header = $lines[0];
        $rows = array_map(fn ($line) => array_combine($header, $line), array_slice($lines, 1));

        $this->assertCount(2, $rows);
        $this->assertSame('<5', $rows[0]['cases']);
        $this->assertSame('', $rows[0]['deaths']);         // deaths among a small count are hidden too
        $this->assertSame('', $rows[0]['incidence_per_100k']);
        $this->assertSame('12', $rows[1]['cases']);
        $this->assertNotContains('facility', $header);       // region level only, never facility rows
        $this->assertDatabaseHas('audit_logs', ['action' => 'export_csv']);
    }

    public function test_fhir_measure_report_carries_numerator_denominator_and_codes(): void
    {
        $response = $this->actingAs($this->clinicianIn('Kigoma'))
            ->get('/api/fhir/MeasureReport?condition_id='.$this->condition('A00')->id.'&from=2026-11-09&to=2026-11-16');

        $response->assertOk();
        $this->assertStringContainsString('application/fhir+json', $response->headers->get('Content-Type'));

        $bundle = $response->json();
        $this->assertSame('Bundle', $bundle['resourceType']);
        $this->assertSame(1, $bundle['total']); // a clinician only gets their own region

        $report = $bundle['entry'][0]['resource'];
        $this->assertSame('MeasureReport', $report['resourceType']);
        $this->assertSame('summary', $report['type']);
        $this->assertSame('TZ-08', $report['subject']['identifier']['value']);
        $this->assertSame('A00', $report['group'][0]['code']['coding'][0]['code']);
        $this->assertSame(15, $report['group'][0]['population'][0]['count']);
        $this->assertSame(2470967, $report['group'][0]['population'][1]['count']);
    }
}
