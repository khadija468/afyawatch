<?php

namespace App\Http\Controllers;

use App\Services\SurveillanceStats;
use App\Support\DashboardFilters;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public pages. These are the only pages search engines may index.
 */
class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function summary(SurveillanceStats $stats): View
    {
        return view('pages.summary', [
            'summary' => $stats->publicSummary(),
            'campaign' => $stats->campaign()['totals'] ?? null,
        ]);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /$',
            'Allow: /summary',
            'Disallow: /dashboard',
            'Disallow: /account',
            'Disallow: /login',
            'Disallow: /api/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $lastWeek = DashboardFilters::latestWeek()->addDays(7)->toDateString();
        $urls = [
            ['loc' => route('home'), 'lastmod' => $lastWeek, 'changefreq' => 'monthly', 'priority' => '1.0'],
        ];

        // The weekly summary is only offered to search engines once it carries real figures.
        if (! config('afyawatch.synthetic_data')) {
            $urls[] = ['loc' => route('summary'), 'lastmod' => $lastWeek, 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        return response()
            ->view('pages.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
