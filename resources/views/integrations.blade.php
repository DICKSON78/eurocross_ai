@extends('layouts.app')

@section('title', 'Integrations | EuroCross')
@section('description', 'EuroCross works with WooCommerce, WordPress, Shopify, Webflow and more. Connect once and translate everywhere your store lives.')

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Integrations</span>
            <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Fits the tools<br><span class="text-rose-500">you already use.</span></h1>
            <p class="ec-lead mt-5 max-w-xl">Connect once and translate everywhere your store lives. EuroCross works alongside your setup — no rebuilding required.</p>
            <a href="#ec-platforms" class="ec-action-light mt-8">Explore platforms <i class="fa-solid fa-arrow-down text-xs" aria-hidden="true"></i></a>
        </div>
        <div class="rounded-3xl border border-plum-900/6 bg-white/75 p-6 shadow-lift backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/apps.svg') }}" alt="Integrations illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>

<section id="ec-platforms" class="ec-wrap py-20">
    <div class="max-w-xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Platforms</span>
        <h2 class="ec-display mt-4 text-3xl">Works where your store lives.</h2>
        <p class="ec-lead mt-3">From WooCommerce to custom builds, EuroCross connects through one simple integration.</p>
    </div>
    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['WooCommerce','fa-cart-shopping'],["WordPress","fa-puzzle-piece"],['Shopify','fa-bag-shopping'],['Webflow','fa-layer-group'],
            ['BigCommerce','fa-store'],['Squarespace','fa-square'],['Wix','fa-globe'],['Custom Code','fa-code'],
        ] as $platform)
        <div class="ec-tile group text-center !p-6">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-rose-50 text-rose-500 transition group-hover:bg-rose-500 group-hover:text-white"><i class="fa-solid {{ $platform[1] }} text-lg" aria-hidden="true"></i></span>
            <p class="mt-4 text-sm font-semibold text-plum-900">{{ $platform[0] }}</p>
        </div>
        @endforeach
    </div>
</section>

<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap grid items-center gap-12 lg:grid-cols-2">
        <div class="rounded-3xl bg-rose-50 p-8">
            <img src="{{ asset('images/illustrations/connected-world.svg') }}" alt="Connected world illustration" class="mx-auto max-h-80 object-contain">
        </div>
        <div>
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Simple connection</span>
            <h2 class="ec-display mt-4 text-3xl">Up and running in minutes.</h2>
            <p class="ec-lead mt-3">One key, one click, and your store starts speaking every language you choose.</p>
            <ul class="mt-6 space-y-3 text-sm text-plum-900/60">
                @foreach (['Install the plugin','Add your API key','Choose your languages','Go live automatically'] as $item)
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap text-center">
        <h2 class="ec-display text-3xl sm:text-4xl">Ready to connect?</h2>
        <p class="mx-auto mt-4 max-w-lg text-base leading-8 text-white/60">Start free today and see how quickly your store can go global.</p>
        <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action !bg-rose-500 mt-8">Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
    </div>
</section>


<section class="ec-wrap py-20">
    <div class="max-w-xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">How it works</span>
        <h2 class="ec-display mt-4 text-3xl">One connection. Every language.</h2>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        @foreach ([
            ['fa-plug','Install','Add EuroCross to your store in a couple of clicks.'],
            ['fa-key','Authenticate','Generate an API key and paste it into your settings.'],
            ['fa-language','Translate','Pick your languages and watch content update automatically.'],
        ] as $step)
            <article class="ec-tile">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $step[0] }}" aria-hidden="true"></i></span>
                <h3 class="mt-5 text-lg font-semibold text-plum-900">{{ $step[1] }}</h3>
                <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $step[2] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="bg-rose-50 py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Under the hood</span>
            <h2 class="ec-display mt-4 text-3xl">Built to fit your stack.</h2>
            <p class="ec-lead mt-3">A clean REST API, secure key handling and clear documentation make EuroCross easy to work with.</p>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach (['REST API for every endpoint','Hashed API keys and rate limits','Clear responses and error messages'] as $item)
                <div class="flex items-start gap-3 rounded-2xl border border-plum-900/6 bg-white p-6"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><i class="fa-solid fa-check" aria-hidden="true"></i></span><p class="text-sm text-plum-900/70">{{ $item }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@endsection
