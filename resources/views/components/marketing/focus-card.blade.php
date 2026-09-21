{{-- Centered card on the dark hero background, for short focused flows (join). --}}
<section class="relative flex min-h-screen items-center overflow-hidden bg-night px-4 pt-28 pb-16">
    <div class="pointer-events-none absolute inset-0">
        <div class="absolute -top-32 -left-24 size-[30rem] rounded-full bg-brand/60 blur-3xl"></div>
        <div class="absolute -right-24 bottom-0 size-[24rem] rounded-full bg-highlight/20 blur-3xl"></div>
    </div>
    <div class="relative mx-auto w-full max-w-lg rounded-3xl bg-white p-8 shadow-2xl sm:p-10">
        {{ $slot }}
    </div>
</section>
