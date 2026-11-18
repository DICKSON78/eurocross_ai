@extends('layouts.app')

@section('title', 'About | EuroCross')
@section('description', 'About EuroCross — helping WooCommerce stores reach European customers.')

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> About</span>
            <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Making global selling<br><span class="text-rose-500">feel local.</span></h1>
            <p class="ec-lead mt-5 max-w-xl">The story and mission behind EuroCross.</p>
        </div>
        <div class="rounded-3xl border border-plum-900/6 bg-white/75 p-6 shadow-lift backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/co-workers.svg') }}" alt="Team illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>

<section class="ec-wrap py-20">
    <div class="mx-auto max-w-2xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Our story</span>
        <h2 class="ec-display mt-4 text-3xl">Built for ambitious store owners.</h2>
        <p class="mt-6 text-base leading-8 text-plum-900/70">EuroCross helps WooCommerce store owners reach European customers by translating product titles and descriptions into up to 15 languages, with a responsive switcher and cached, server-side delivery.</p>
        <p class="mt-5 text-base leading-8 text-plum-900/55">Our mission is to make multilingual selling simple: connect your store, choose your languages, and keep your catalog readable in every market.</p>
    </div>
    <div class="mt-14 grid gap-5 sm:grid-cols-3">
        @foreach ([['15','Languages supported'],['7 days','Free trial'],['3','Simple plans']] as $stat)
            <div class="ec-tile text-center"><div class="ec-stat-value">{{ $stat[0] }}</div><p class="mt-1 text-sm text-plum-900/50">{{ $stat[1] }}</p></div>
        @endforeach
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap text-center">
        <h2 class="ec-display text-3xl sm:text-4xl">Ready to go global?</h2>
        <p class="mx-auto mt-4 max-w-lg text-white/60">Start free today.</p>
        <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action !bg-rose-500 mt-8">Start free trial</a>
    </div>
</section>


<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">What we believe</span>
            <h2 class="ec-display mt-4 text-3xl">Global growth should be simple.</h2>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['fa-globe','Language is access','Customers buy in their own language. We make that possible without extra work.'],
                ['fa-bolt','Speed matters','Cached translations and server-side requests keep the storefront responsive.'],
                ['fa-lock','Clarity is trust','Clear plan limits, transparent pricing and careful credential handling.'],
            ] as $item)
                <article class="ec-tile">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $item[0] }}" aria-hidden="true"></i></span>
                    <h3 class="mt-5 text-lg font-semibold text-plum-900">{{ $item[1] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $item[2] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-rose-50 py-20">
    <div class="ec-wrap grid gap-12 lg:grid-cols-2">
        <div class="rounded-3xl bg-white p-8"><img src="{{ asset('images/illustrations/connected-world.svg') }}" alt="Global illustration" class="mx-auto max-h-80 object-contain"></div>
        <div>
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Our approach</span>
            <h2 class="ec-display mt-4 text-3xl">Practical tools, not complexity.</h2>
            <p class="ec-lead mt-3">We focus on what actually helps a store go international: accurate product translation, simple language management and reliable performance.</p>
            <ul class="mt-6 space-y-3 text-sm text-plum-900/60">
                @foreach (['Clear setup with no technical work','Transparent, predictable pricing','Features that grow with your store'] as $item)
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

@endsection
