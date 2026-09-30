<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Sitemap') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Generated fresh on every request — new job postings appear here as soon as they\'re published, nothing to regenerate manually.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700" x-data="{ copied: false }">
        <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Sitemap URL') }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <code class="min-w-0 flex-1 truncate rounded-lg bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-900">{{ $sitemapUrl }}</code>
            <flux:button size="sm" icon="clipboard" x-on:click="navigator.clipboard.writeText(@js($sitemapUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-text="copied ? '{{ __('Copied') }}' : '{{ __('Copy link') }}'"></span>
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$sitemapUrl" target="_blank">
                {{ __('View') }}
            </flux:button>
        </div>
        <p class="mt-3 text-xs text-neutral-500">
            {{ __('Submit this URL in Google Search Console (Sitemaps) and Bing Webmaster Tools so new pages get crawled without waiting for organic discovery.') }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Total URLs') }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $total }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Static pages') }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $staticCount }}</p>
            <p class="text-xs text-neutral-500">{{ __('changefreq: daily') }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
            <p class="text-xs uppercase tracking-wide text-neutral-500">{{ __('Open job postings') }}</p>
            <p class="mt-2 text-2xl font-semibold">{{ $alwaysCount }}</p>
            <p class="text-xs text-neutral-500">{{ __('changefreq: always') }}</p>
        </div>
    </div>

    <flux:callout variant="secondary" icon="information-circle"
        :heading="__('Per-tenant careers boards aren\'t included yet')"
        :text="__('Those endpoints currently return JSON for a client-rendered page rather than a crawlable HTML page, so there\'s nothing yet for a sitemap to point search engines at.')" />
</div>
