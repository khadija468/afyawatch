<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->regionReport('Kigoma', 'B05', '2026-11-16', 7);
        $this->regionReport('Kagera', 'B05', '2026-11-16', 12);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->get('/api/export.csv')->assertRedirect('/login');
    }

    public function test_a_manager_sees_every_region_and_the_campaign(): void
    {
        $response = $this->actingAs($this->manager())->getJson('/api/dashboard?condition_id='.$this->condition('B05')->id);

        $response->assertOk()
            ->assertJsonPath('filters.scope_label', 'Tanzania Mainland')
            ->assertJsonPath('kpis.cases', 19);

        $this->assertCount(26, collect($response->json('regions'))->where('in_view', true));
        $this->assertNotNull($response->json('campaign'));
    }

    public function test_a_clinician_only_receives_figures_for_their_own_region(): void
    {
        $response = $this->actingAs($this->clinicianIn('Kigoma'))
            ->getJson('/api/dashboard?condition_id='.$this->condition('B05')->id);

        $response->assertOk()
            ->assertJsonPath('filters.scope_label', 'Kigoma')
            ->assertJsonPath('kpis.cases', 7)
            ->assertJsonPath('campaign', null);

        $kagera = collect($response->json('regions'))->firstWhere('name', 'Kagera');
        $this->assertFalse($kagera['in_view']);
        $this->assertNull($kagera['cases']);
    }

    public function test_a_clinician_cannot_ask_for_another_region(): void
    {
        $this->actingAs($this->clinicianIn('Kigoma'))
            ->getJson('/api/dashboard?region_id='.$this->region('Kagera')->id)
            ->assertForbidden();
    }

    public function test_a_clinician_cannot_acknowledge_an_alert_outside_their_region(): void
    {
        $condition = $this->condition('B05');
        $alert = Alert::create([
            'alert_rule_id' => AlertRule::where('condition_id', $condition->id)->value('id'),
            'condition_id' => $condition->id,
            'region_id' => $this->region('Kagera')->id,
            'week_start' => '2026-11-16',
            'observed' => 12,
            'limit_value' => 5,
            'severity' => 'warning',
        ]);

        $this->actingAs($this->clinicianIn('Kigoma'))
            ->postJson("/api/alerts/{$alert->id}/acknowledge")
            ->assertForbidden();

        $this->actingAs($this->clinicianIn('Kagera'))
            ->postJson("/api/alerts/{$alert->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('status', 'acknowledged');

        $this->assertDatabaseHas('audit_logs', ['action' => 'alert_acknowledged']);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->manager(['email' => 'boss@example.org']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'boss@example.org', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'boss@example.org', 'password' => 'wrong'])->assertStatus(429);
        $this->assertDatabaseCount('audit_logs', 5);
    }

    public function test_a_user_can_sign_in_and_is_audited(): void
    {
        $this->manager(['email' => 'boss@example.org']);

        $this->post('/login', ['email' => 'boss@example.org', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login']);
    }
}
