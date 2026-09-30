<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Support\Recruitment\PublicJobs;
use Illuminate\Support\Collection;

/**
 * Builds the central marketing site's XML sitemap. Static pages use `daily`
 * — content that rarely changes, but still worth a regular recheck. Open job
 * postings use `always`: vacancies open, fill and close often, so a fresh
 * posting should get crawled as soon as possible rather than wait out a
 * slower default. This deliberately covers only central, server-rendered
 * HTML pages — the per-tenant careers board (`careers.index`/`careers.show`)
 * is a JSON API today with no page of its own to index.
 */
class SitemapGenerator
{
    /**
     * @return array<int, array{route: string, priority: string}>
     */
    private const STATIC_PAGES = [
        ['route' => 'home', 'priority' => '1.0'],
        ['route' => 'features', 'priority' => '0.8'],
        ['route' => 'pricing', 'priority' => '0.8'],
        ['route' => 'jobs.index', 'priority' => '0.9'],
        ['route' => 'about', 'priority' => '0.6'],
        ['route' => 'docs', 'priority' => '0.6'],
        ['route' => 'contact', 'priority' => '0.5'],
        ['route' => 'applications.track', 'priority' => '0.4'],
        ['route' => 'quote', 'priority' => '0.7'],
        ['route' => 'privacy', 'priority' => '0.3'],
    ];

    /**
     * @return Collection<int, array{loc: string, changefreq: string, priority: string, lastmod: ?string}>
     */
    public function entries(PublicJobs $jobs): Collection
    {
        $static = collect(self::STATIC_PAGES)->map(fn (array $page): array => [
            'loc' => route($page['route']),
            'changefreq' => 'daily',
            'priority' => $page['priority'],
            'lastmod' => null,
        ]);

        $vacancies = $jobs->list()->map(fn ($vacancy): array => [
            'loc' => route('jobs.show', ['organization' => $vacancy->tenant->slug, 'vacancy' => $vacancy->slug]),
            'changefreq' => 'always',
            'priority' => '0.5',
            'lastmod' => ($vacancy->published_at ?? $vacancy->updated_at)?->toAtomString(),
        ]);

        return $static->concat($vacancies)->values();
    }
}
