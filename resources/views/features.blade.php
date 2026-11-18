@extends('layouts.app')

@section('title', 'Features | EuroCross')
@section('description', 'Explore EuroCross features: AI product translation, 15 languages, a responsive switcher, WooCommerce support, usage analytics and security controls.')

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Platform</span>
            <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Built for stores that<br><span class="text-rose-500">think global.</span></h1>
            <p class="ec-lead mt-5 max-w-xl">A focused WooCommerce translation toolkit: AI product copy, language management, caching and clear usage reporting.</p>
            <a href="#ec-feature-grid" class="ec-action-light mt-8">Explore features <i class="fa-solid fa-arrow-down text-xs" aria-hidden="true"></i></a>
        </div>
        <div class="rounded-3xl border border-plum-900/6 bg-white/75 p-6 shadow-lift backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/ai-answers.svg') }}" alt="Features illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>

<section id="ec-feature-grid" class="ec-wrap py-20">
    <div class="max-w-xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Capabilities</span>
        <h2 class="ec-display mt-4 text-3xl">Everything working together.</h2>
        <p class="ec-lead mt-3">A small, deliberate feature set built around translating WooCommerce catalogues reliably.</p>
    </div>
    <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['fa-language','Smart translation','Context-aware translations for product titles and descriptions across 15 languages.'],
            ['fa-globe','Language management','Choose allowed languages and give shoppers a responsive language switcher.'],
            ['fa-cart-shopping','WooCommerce focus','Works with the product fields WooCommerce already uses, without changing prices or stock.'],
            ['fa-bolt','Caching','Translated text is cached locally so repeat page views stay fast.'],
            ['fa-chart-line','Usage visibility','See plan limits, translation usage and subscription status in your dashboard.'],
            ['fa-lock','Security','Hashed API keys, per-plan rate limits and administrator-only site settings.'],
        ] as $feature)
        <article class="ec-tile">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $feature[0] }}" aria-hidden="true"></i></span>
            <h3 class="mt-5 text-lg font-semibold text-plum-900">{{ $feature[1] }}</h3>
            <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $feature[2] }}</p>
        </article>
        @endforeach
    </div>
</section>

<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap grid items-center gap-12 lg:grid-cols-2">
        <div class="rounded-3xl bg-rose-50 p-8"><img src="{{ asset('images/illustrations/all-the-data.svg') }}" alt="Data illustration" class="mx-auto max-h-80 object-contain"></div>
        <div>
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">One dashboard</span>
            <h2 class="ec-display mt-4 text-3xl">Stay in control.</h2>
            <p class="ec-lead mt-3">Check your plan, languages, translation usage and billing state without leaving the dashboard.</p>
            <ul class="mt-6 space-y-3 text-sm text-plum-900/60">
                @foreach (['Plan and language limits','Translation usage and remaining words','Subscription and billing status','Connected-site connection status'] as $item)
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap text-center">
        <h2 class="ec-display text-3xl sm:text-4xl">Try it free for 7 days.</h2>
        <p class="mx-auto mt-4 max-w-lg text-white/60">No card required. Cancel anytime.</p>
        <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action !bg-rose-500 mt-8">Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
    </div>
</section>


<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Why it matters</span>
            <h2 class="ec-display mt-4 text-3xl">Numbers that move your business forward.</h2>
            <p class="ec-lead mt-3">Go further with less work — and keep every market in sync.</p>
        </div>
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['15','Languages','Reach customers in their own language.'],['3–15','Per plan','Choose the language count that fits your store.'],['60–1,000','Requests/min','Plans that scale with your catalogue.'],['7 days','Free trial','Try translation before subscribing.']] as $stat)
                <div class="ec-tile text-center"><div class="ec-stat-value">{{ $stat[0] }}</div><h3 class="mt-2 text-sm font-semibold text-plum-900">{{ $stat[1] }}</h3><p class="mt-1 text-xs text-plum-900/50">{{ $stat[2] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

<section class="ec-wrap py-20">
    <div class="max-w-xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Who it's for</span>
        <h2 class="ec-display mt-4 text-3xl">Built for every kind of store.</h2>
    </div>
    <div class="mt-10 grid gap-5 md:grid-cols-3">
        @foreach ([
            ['fa-store','Store owners','Translate your catalog and reach new customers without hiring a team.'],
            ['fa-users','Agencies','Install the plugin on client stores with a separate EuroCross account for each site.'],
            ['fa-code','Developers','Connect through a clean API and build custom translation workflows.'],
        ] as $item)
            <article class="ec-tile">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $item[0] }}" aria-hidden="true"></i></span>
                <h3 class="mt-5 text-lg font-semibold text-plum-900">{{ $item[1] }}</h3>
                <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $item[2] }}</p>
            </article>
        @endforeach
    </div>
</section>

<section class="bg-rose-50 py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Peace of mind</span>
            <h2 class="ec-display mt-4 text-3xl">Optional VAT tools, clearly labelled.</h2>
            <p class="ec-lead mt-3">EuroCross can display VAT treatment information and call VIES, but it does not provide legal advice or guarantee tax compliance.</p>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach (['VAT number field at checkout','VIES number validation when available','Informational OSS notes in your dashboard'] as $item)
                <div class="flex items-start gap-3 rounded-2xl border border-plum-900/6 bg-white p-6"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><i class="fa-solid fa-check" aria-hidden="true"></i></span><p class="text-sm text-plum-900/70">{{ $item }}</p></div>
            @endforeach
        </div>
    </div>
</section>

@endsection
