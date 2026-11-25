<?php

namespace Tests\Feature;

use App\Mail\AlertDigestMail;
use App\Mail\OutbreakAlertMail;
use App\Models\Alert;
use App\Services\AlertEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class AlertEvaluatorTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    private AlertEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->evaluator = app(AlertEvaluator::class);
        Mail::fake();
    }

    private function evaluate(string $week)
    {
        return $this->evaluator->evaluate(CarbonImmutable::parse($week));
    }

    public function test_one_cholera_case_raises_a_critical_alert_for_the_right_people_only(): void
    {
        $manager = $this->manager();
        $kigoma = $this->clinicianIn('Kigoma');
        $kagera = $this->clinicianIn('Kagera');
        $muted = $this->manager(['alert_preference' => 'off']);

        $this->regionReport('Kigoma', 'A00', '2026-11-16', 1);

        $alerts = $this->evaluate('2026-11-16');

        $this->assertCount(1, $alerts);
        $this->assertSame('critical', $alerts->first()->severity);
        Mail::assertSent(OutbreakAlertMail::class, fn ($mail) => $mail->hasTo($manager->email));
        Mail::assertSent(OutbreakAlertMail::class, fn ($mail) => $mail->hasTo($kigoma->email));
        Mail::assertNotSent(OutbreakAlertMail::class, fn ($mail) => $mail->hasTo($kagera->email));
        Mail::assertNotSent(OutbreakAlertMail::class, fn ($mail) => $mail->hasTo($muted->email));
        Mail::assertSentCount(2);
    }

    public function test_running_the_same_week_twice_creates_and_sends_nothing_new(): void
    {
        $this->manager();
        $this->regionReport('Kigoma', 'A00', '2026-11-16', 3);

        $this->evaluate('2026-11-16');
        $second = $this->evaluate('2026-11-16');

        $this->assertCount(0, $second);
        $this->assertSame(1, Alert::count());
        Mail::assertSentCount(1);
    }

    public function test_cooldown_holds_back_repeat_emails_unless_cases_double(): void
    {
        $this->manager();
        $this->regionReport('Kagera', 'B05', '2026-10-26', 6);  // first alert: emailed
        $this->regionReport('Kagera', 'B05', '2026-11-02', 9);  // under double: held back
        $this->regionReport('Kagera', 'B05', '2026-11-09', 13); // double of 6: emailed

        $this->evaluate('2026-10-26');
        $this->evaluate('2026-11-02');
        $this->evaluate('2026-11-09');

        $alerts = Alert::orderBy('week_start')->get();
        $this->assertCount(3, $alerts);
        $this->assertNotNull($alerts[0]->emailed_at);
        $this->assertNull($alerts[1]->emailed_at);
        $this->assertStringStartsWith('Cooldown', $alerts[1]->email_skipped_reason);
        $this->assertNotNull($alerts[2]->emailed_at);
        Mail::assertSentCount(2);
    }

    public function test_statistical_rule_ignores_normal_variation_but_flags_a_spike(): void
    {
        $this->manager();
        $weeks = ['2026-09-21', '2026-09-28', '2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26', '2026-11-02', '2026-11-09'];
        foreach ([20, 24, 18, 22, 21, 19, 23, 20] as $i => $cases) {
            $this->regionReport('Mwanza', 'A01.0', $weeks[$i], $cases);
        }

        $this->regionReport('Mwanza', 'A01.0', '2026-11-16', 27); // within mean + 3 SD (about 21 + 14)
        $this->assertCount(0, $this->evaluate('2026-11-16'));

        $this->regionReport('Mwanza', 'A01.0', '2026-11-23', 60); // far above
        $alerts = $this->evaluate('2026-11-23');

        $this->assertCount(1, $alerts);
        $this->assertSame('warning', $alerts->first()->severity);
        $this->assertEqualsWithDelta(21.0, $alerts->first()->expected, 1.0);
    }

    public function test_digest_users_get_warnings_once_a_day_instead_of_immediately(): void
    {
        $digest = $this->clinicianIn('Kagera', ['alert_preference' => 'digest']);
        $this->regionReport('Kagera', 'B05', '2026-11-16', 8);

        $this->evaluate('2026-11-16');
        Mail::assertNotSent(OutbreakAlertMail::class);

        $this->artisan('alerts:digest')->assertSuccessful();
        Mail::assertSent(AlertDigestMail::class, fn ($mail) => $mail->hasTo($digest->email) && $mail->alerts->count() === 1);
    }

    public function test_historical_evaluation_never_sends_email(): void
    {
        $this->manager();
        $this->regionReport('Kigoma', 'A00', '2026-11-16', 4);

        $this->evaluator->evaluate(CarbonImmutable::parse('2026-11-16'), notify: false);

        Mail::assertNothingSent();
        $this->assertSame('Historical evaluation: no email sent', Alert::first()->email_skipped_reason);
    }
}
