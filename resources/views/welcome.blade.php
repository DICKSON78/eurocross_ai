@extends('layouts.app')

@section('title', 'Home | EuroCross')
@section('description', 'EuroCross translates your WooCommerce store into 15 languages with SEO and VAT compliance built in. Start your 7-day free trial.')

@push('head')
    <script src="https://gumroad.com/js/gumroad.js"></script>
@endpush

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 sm:pt-24 lg:pt-28 bg-cream">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-48 -left-40 h-[30rem] w-[30rem] rounded-full bg-plum-700/10 blur-3xl" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
        <div class="ec-scroll-reveal">
            <h1 class="ec-display text-[2.9rem] sm:text-6xl lg:text-[4.1rem]">
                Your store, in<br><span class="text-rose-500">ten languages.</span>
            </h1>
            <p class="ec-lead mt-6 max-w-xl">Translate products, SEO and checkout content automatically. EuroCross handles the languages — you keep growing the business.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action">Start 7-day free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
                <a href="#ec-how" class="ec-action-light">See how it works</a>
            </div>
            <div class="mt-10 flex flex-wrap items-center gap-x-7 gap-y-3 text-sm text-plum-900/60">
                <span class="flex items-center gap-2"><i class="fa-solid fa-star text-rose-500" aria-hidden="true"></i> 4.9/5 rating</span>
                <span class="flex items-center gap-2"><i class="fa-solid fa-credit-card text-rose-500" aria-hidden="true"></i> No card required</span>
                <span class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-rose-500" aria-hidden="true"></i> Cancel anytime</span>
            </div>
        </div>

        <div class="ec-scroll-reveal relative" style="transition-delay:120ms">
            <div class="rounded-3xl border border-plum-900/8 bg-white p-5 shadow-lift sm:p-7">
                <div class="flex items-center justify-between border-b border-plum-900/5 pb-4">
                    <span class="ec-badge"><i class="fa-solid fa-language" aria-hidden="true"></i> Live translation</span>
                    <span class="flex gap-1.5" aria-hidden="true"><i class="fa-solid fa-circle text-rose-300 text-[8px]"></i><i class="fa-solid fa-circle text-plum-900/15 text-[8px]"></i><i class="fa-solid fa-circle text-plum-900/15 text-[8px]"></i></span>
                </div>
                <div class="mt-5 rounded-2xl bg-rose-50/70 p-5">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-plum-600">Product title</p>
                    <p class="mt-1.5 text-sm text-plum-900/45">Handcrafted leather bag</p>
                    <div class="my-4 ec-rule"></div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-plum-600">Translated</p>
                    <p class="mt-1.5 text-base font-semibold text-plum-900">Sac en cuir artisanal</p>
                    <p class="mt-0.5 text-xs text-plum-900/45">French · France</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (['EN','FR','DE','ES','IT','PT','NL','AR','ZH','JA'] as $code)
                        <span class="rounded-lg border border-plum-900/8 px-2.5 py-1 text-[11px] font-semibold text-plum-900/55">{{ $code }}</span>
                    @endforeach
                </div>
            </div>
            <div class="absolute -bottom-6 -left-4 hidden items-center gap-3 rounded-2xl border border-plum-900/8 bg-white px-4 py-3 shadow-lg sm:flex">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-rose-100 text-rose-500"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                <div><p class="text-xs font-semibold text-plum-900">SEO optimized</p><p class="text-[10px] text-plum-900/45">Ready to rank locally</p></div>
            </div>
        </div>
    </div>

    <div id="ec-how" class="ec-wrap relative mt-16">
        <div class="grid gap-4 rounded-2xl border border-plum-900/6 bg-white/70 p-5 backdrop-blur-sm sm:grid-cols-3 sm:p-6">
            @foreach ([
                ['fa-plug','Connect','Install the plugin on your WooCommerce store.'],
                ['fa-globe','Choose languages','Pick the markets you want to reach.'],
                ['fa-arrow-up-right-dots','Grow','Translations go live automatically.'],
            ] as $i => $step)
            <div class="flex items-start gap-3 sm:block">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><i class="fa-solid {{ $step[0] }}" aria-hidden="true"></i></span>
                <div class="sm:mt-3">
                    <p class="text-sm font-semibold text-plum-900"><span class="mr-2 text-rose-400">0{{ $i + 1 }}</span>{{ $step[1] }}</p>
                    <p class="mt-1 text-xs leading-5 text-plum-900/50">{{ $step[2] }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="border-y border-plum-900/5 bg-white py-10">
    <div class="ec-wrap">
        <p class="text-center text-[11px] font-semibold uppercase tracking-[0.22em] text-plum-900/40">One platform for every part of going global</p>
        <div class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([['fa-language','Translation'],['fa-magnifying-glass','SEO'],['fa-file-invoice','VAT & OSS'],['fa-chart-line','Analytics']] as $item)
                <div class="flex items-center justify-center gap-2 text-plum-900/55"><i class="fa-solid {{ $item[0] }} text-rose-500" aria-hidden="true"></i><span class="text-sm font-medium">{{ $item[1] }}</span></div>
            @endforeach
        </div>
    </div>
</section>

<section class="ec-wrap py-20 sm:py-24">
    <div class="max-w-2xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">What you get</span>
        <h2 class="ec-display mt-4 text-3xl sm:text-4xl">Everything your store needs to cross borders.</h2>
        <p class="ec-lead mt-4">No plugins stacking up, no manual spreadsheets. Just one clear path from local to international.</p>
    </div>
    <div class="mt-12 grid gap-5 md:grid-cols-3">
        @foreach ([
            ['fa-language','Speak their language','Natural translations across 15 languages for products, categories and store content.'],
            ['fa-compass','Get found locally','Meta titles, descriptions and URLs translated for search engines in every market.'],
            ['fa-receipt','Sell compliantly','28 EU VAT rates, VIES validation and OSS reports built into your workflow.'],
        ] as $i => $card)
        <article class="ec-tile ec-scroll-reveal" style="transition-delay:{{ $i * 80 }}ms">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $card[0] }}" aria-hidden="true"></i></span>
            <h3 class="mt-5 text-lg font-semibold text-plum-900">{{ $card[1] }}</h3>
            <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $card[2] }}</p>
        </article>
        @endforeach
    </div>
</section>

<section id="ec-pricing" class="ec-wrap py-20 sm:py-24">
    <div class="text-center">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Pricing</span>
        <h2 class="ec-display mt-4 text-3xl sm:text-4xl">Simple plans. No surprises.</h2>
        <p class="ec-lead mx-auto mt-4 max-w-xl">7 days free on every plan. Upgrade, downgrade or cancel whenever you like.</p>
    </div>
    <div class="mt-12 grid gap-5 lg:grid-cols-3">
        @foreach ([
            ['Starter','€12','50,000 words / month','3 languages','60 requests / min','Basic VAT',config('payment.gumroad.checkout_starter'),false],
            ['Growth','€25','100,000 words / month','5 languages','300 requests / min','Full OSS + VIES',config('payment.gumroad.checkout_growth'),true],
            ['Scale','€65','300,000 words / month','15 languages','1,000 requests / min','Full OSS + VIES',config('payment.gumroad.checkout_scale'),false],
        ] as $plan)
        <div class="ec-tile flex flex-col {{ $plan[7] ? '!border-rose-400 ring-1 ring-rose-400' : '' }}">
            @if($plan[7])<span class="absolute right-5 top-5 rounded-full bg-rose-500 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white">Popular</span>@endif
            <h3 class="text-lg font-semibold text-plum-900">{{ $plan[0] }}</h3>
            <div class="mt-3 text-4xl font-bold text-plum-900">{{ $plan[1] }}<span class="text-sm font-normal text-plum-900/40">/month</span></div>
            <ul class="mt-6 mb-6 flex-1 space-y-3 border-t border-plum-900/6 pt-6 text-sm text-plum-900/60">
                @foreach ([$plan[2],$plan[3],$plan[4],$plan[5]] as $feature)
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="{{ $plan[6] }}" data-plan="{{ strtolower($plan[0]) }}" class="ec-action mt-auto w-full">Choose {{ $plan[0] }}</a>
        </div>
        @endforeach
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap grid items-center gap-10 md:grid-cols-[1.2fr_0.8fr]">
        <div>
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-400">Ready when you are</span>
            <h2 class="ec-display mt-4 text-3xl sm:text-5xl">Give your store a bigger world.</h2>
            <p class="mt-5 max-w-xl text-base leading-8 text-white/60">Start free today. Ten languages, built-in SEO, and VAT compliance are waiting.</p>
            <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action !bg-rose-500 mt-8 !hover:!bg-rose-400">Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
        </div>
        <div class="rounded-3xl bg-cream/10 p-6 ring-1 ring-white/10 backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/around-the-world.svg') }}" alt="Global reach illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>


@guest
<script>
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-plan]');
    if (!link) return;
    event.preventDefault();
    window.location.href = '/login?intended=' + encodeURIComponent('/pricing?subscribe=' + link.dataset.plan + '#ec-pricing');
});
</script>
@endguest
<script>
document.addEventListener('DOMContentLoaded', function () {
    var plan = new URLSearchParams(window.location.search).get('subscribe');
    if (!plan) return;
    var link = document.querySelector('a[data-plan="' + plan + '"]');
    if (link) link.click();
});
</script>

@endsection
