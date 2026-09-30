<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Services\Seo\SitemapGenerator;
use App\Support\Recruitment\PublicJobs;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The sitemap itself is generated fresh on every crawler request (see
 * App\Http\Controllers\SitemapController) — there's nothing to persist or
 * regenerate here. This page just surfaces the URL to copy and a live
 * breakdown of what it currently contains, so an operator can sanity-check
 * it before submitting it to Google Search Console / Bing Webmaster Tools.
 */
class Sitemap extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::SitemapManage);
    }

    public function render(SitemapGenerator $generator, PublicJobs $jobs): View
    {
        $entries = $generator->entries($jobs);

        return view('livewire.platform.sitemap', [
            'sitemapUrl' => route('sitemap'),
            'staticCount' => $entries->where('changefreq', 'daily')->count(),
            'alwaysCount' => $entries->where('changefreq', 'always')->count(),
            'total' => $entries->count(),
        ]);
    }
}
