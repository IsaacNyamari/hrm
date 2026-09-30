<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Seo\SitemapGenerator;
use App\Support\Recruitment\PublicJobs;
use Illuminate\Http\Response;

/**
 * Served fresh on every request — no caching — so a newly published vacancy
 * or a status change is reflected immediately, not on the next cache cycle.
 */
class SitemapController extends Controller
{
    public function __invoke(SitemapGenerator $generator, PublicJobs $jobs): Response
    {
        $xml = view('sitemap.index', ['entries' => $generator->entries($jobs)])->render();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
