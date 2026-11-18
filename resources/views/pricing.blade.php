@extends('layouts.app')

@section('title', 'Pricing | EuroCross')
@section('description', 'EuroCross plans: Starter €12, Growth €25, Scale €65 monthly. 7-day free trial on every plan.')

@push('head')
    <script src="https://gumroad.com/js/gumroad.js"></script>
@endpush

@section('content')

@php
    $plans = [
        'starter' => ['name' => 'Starter', 'key' => 'starter', 'popular' => false],
        'growth' => ['name' => 'Growth', 'key' => 'growth', 'popular' => true],
        'scale' => ['name' => 'Scale', 'key' => 'scale', 'popular' => false],
    ];
    $checkoutUrl = function (string $key): string {
        $url = (string) config("payment.gumroad.checkout_{$key}");

        if (auth()->check()) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator.'email='.rawurlencode(auth()->user()->email).'&wanted=true';
        }

        return $url;
    };
@endphp

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative text-center">
        <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Pricing</span>
        <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Pick the plan that<br><span class="text-rose-500">fits your growth.</span></h1>
        <p class="ec-lead mx-auto mt-5 max-w-2xl">7-day free trial. No card required to start. Cancel whenever you want.</p>
        @auth
            <p class="mt-4 inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-plum-700">
                <i class="fa-solid fa-circle-info text-rose-500" aria-hidden="true"></i>
                Use the same email as your EuroCross account at checkout.
            </p>
        @endauth
    </div>
</section>

<section class="ec-wrap pb-20">
    <div class="grid gap-5 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $settings = config("payment.plans.{$plan['key']}", []);
                $features = $settings['features'] ?? [];
            @endphp
            <div class="ec-tile flex flex-col {{ $plan['popular'] ? '!border-rose-400 ring-1 ring-rose-400' : '' }}">
                @if($plan['popular'])<span class="absolute right-5 top-5 rounded-full bg-rose-500 px-3 py-1 text-[10px] font-bold uppercase text-white">Popular</span>@endif
                <h2 class="text-lg font-semibold text-plum-900">{{ $plan['name'] }}</h2>
                <div class="mt-3 text-4xl font-bold text-plum-900">€{{ number_format((float) ($settings['price'] ?? 0), 0) }}<span class="text-sm font-normal text-plum-900/40">/month</span></div>
                <ul class="mt-6 mb-6 flex-1 space-y-3 border-t border-plum-900/6 pt-6 text-sm text-plum-900/60">
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ number_format((int) ($features['words'] ?? 0)) }} words / month</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ (int) ($features['languages'] ?? 0) }} languages</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ (int) ($settings['rate_limit'] ?? 0) }} requests / min</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ ($features['vat'] ?? '') === 'full' ? 'Full OSS + VIES' : 'Basic VAT' }}</li>
                    <li class="flex items-center gap-2"><i class="fa-solid {{ ($features['chatbot'] ?? false) ? 'fa-check text-rose-500' : 'fa-minus text-plum-900/30' }}" aria-hidden="true"></i>{{ ($features['chatbot'] ?? false) ? 'Chatbot included' : 'No chatbot' }}</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>7-day free trial</li>
                </ul>
                <a href="{{ $checkoutUrl($plan['key']) }}" data-plan="{{ $plan['key'] }}" class="ec-action mt-auto w-full">Subscribe to {{ $plan['name'] }}</a>
            </div>
        @endforeach
    </div>
    <p class="mt-6 text-center text-xs text-plum-900/40">Secure checkout powered by Gumroad. Card details are entered on Gumroad, never on our site.</p>
</section>

<section class="border-y border-plum-900/5 bg-white py-16 text-center">
    <div class="ec-wrap">
        <img src="{{ asset('images/illustrations/pricing-page.svg') }}" alt="Pricing illustration" class="mx-auto mb-6 h-36 object-contain">
        <h2 class="ec-display text-3xl">Not sure yet?</h2>
        <p class="ec-lead mx-auto mt-3 max-w-lg">Try EuroCross free for 7 days and see the difference for yourself.</p>
        <a href="{{ $checkoutUrl('starter') }}" class="ec-action mt-6">Start free trial</a>
    </div>
</section>

<section class="ec-wrap py-20">
    <div class="max-w-xl">
        <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">What's included</span>
        <h2 class="ec-display mt-4 text-3xl">Every plan covers the essentials.</h2>
        <p class="ec-lead mt-3">Upgrade only when you need more volume, languages or speed.</p>
    </div>
    <div class="mt-10 overflow-hidden rounded-2xl border border-plum-900/6 bg-white">
        <div class="grid grid-cols-4 border-b border-plum-900/6 bg-rose-50 px-5 py-4 text-xs font-semibold uppercase tracking-wider text-plum-700"><span>Feature</span><span>Starter</span><span>Growth</span><span>Scale</span></div>
        @foreach ([
            ['Words per month','50,000','100,000','300,000'],
            ['Languages','3','5','15'],
            ['Rate limit','60/min','300/min','1,000/min'],
            ['VAT & OSS','Basic','Full','Full'],
            ['Chatbot','—','Included','Included'],
            ['Free trial','7 days','7 days','7 days'],
        ] as $row)
            <div class="grid grid-cols-4 border-b border-plum-900/5 px-5 py-4 text-sm last:border-0"><span class="font-medium text-plum-900">{{ $row[0] }}</span><span class="text-plum-900/60">{{ $row[1] }}</span><span class="text-plum-900/60">{{ $row[2] }}</span><span class="text-plum-900/60">{{ $row[3] }}</span></div>
        @endforeach
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap text-center">
        <h2 class="ec-display text-3xl sm:text-4xl">Ready to go global?</h2>
        <a href="{{ $checkoutUrl('growth') }}" class="ec-action !bg-rose-500 mt-8">Start free trial</a>
    </div>
</section>


@guest
<script>
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-plan]');
    if (!link) return;
    event.preventDefault();
    window.location.href = '/login?intended=' + encodeURIComponent('/pricing?subscribe=' + link.dataset.plan);
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
