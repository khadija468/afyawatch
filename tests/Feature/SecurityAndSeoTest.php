<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsSurveillanceData;
use Tests\TestCase;

class SecurityAndSeoTest extends TestCase
{
    use BuildsSurveillanceData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->regionReport('Kigoma', 'B05', '2026-11-16', 2);
    }

    public function test_hsts_is_sent_over_https_but_not_over_plain_http(): void
    {
        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_plain_http_is_redirected_to_https_when_forced(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://localhost/summary')->assertRedirect('https://localhost/summary')->assertStatus(301);
    }

    public function test_every_response_carries_the_baseline_security_headers(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $this->assertStringContainsString("frame-ancestors 'none'", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_private_pages_tell_search_engines_not_to_index_them(): void
    {
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->actingAs($this->manager())
            ->get('/dashboard')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_robots_and_sitemap_expose_only_public_pages(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        foreach (['/dashboard', '/api/', '/login', '/account'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}", $robots);
        }
        $this->assertStringContainsString('Sitemap: ', $robots);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $sitemap);
        $this->assertStringNotContainsString('dashboard', $sitemap);
        $this->assertStringNotContainsString('/summary', $sitemap); // synthetic figures stay out of search
    }

    public function test_public_summary_is_labelled_synthetic_and_hides_small_counts(): void
    {
        $this->get('/summary')
            ->assertOk()
            ->assertSee('Demonstration data')
            ->assertSee('fewer than 5')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('"@type":"Dataset"', false);
    }

    public function test_summary_becomes_indexable_once_real_data_is_connected(): void
    {
        config(['afyawatch.synthetic_data' => false]);

        $this->get('/summary')->assertDontSee('Demonstration data')->assertDontSee('noindex', false);
        $this->assertStringContainsString('/summary', $this->get('/sitemap.xml')->getContent());
    }
}
