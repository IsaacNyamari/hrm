@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

<section class="relative overflow-hidden bg-night pt-32 pb-20 text-white sm:pt-40">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-32 left-1/4 size-[28rem] rounded-full bg-brand/60 blur-3xl"></div>
        <div class="absolute -right-24 bottom-0 size-[22rem] rounded-full bg-highlight/20 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgba(255,255,255,0.07)_1px,transparent_0)] [background-size:28px_28px]"></div>
    </div>
    <div class="relative mx-auto max-w-3xl px-4 text-center sm:px-6">
        @if ($eyebrow)
            <p class="text-sm font-bold uppercase tracking-widest text-highlight">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-3 text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-5 text-lg leading-relaxed text-zinc-300">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
