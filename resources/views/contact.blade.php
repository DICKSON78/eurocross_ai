@extends('layouts.app')

@section('title', 'Contact | EuroCross')
@section('description', 'Contact EuroCross for help with translation, billing or VAT compliance.')

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Contact</span>
            <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Let's talk<span class="text-rose-500">.</span></h1>
            <p class="ec-lead mt-5 max-w-xl">Questions about EuroCross? We're here to help.</p>
        </div>
        <div class="rounded-3xl border border-plum-900/6 bg-white/75 p-6 shadow-lift backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/contact-us.svg') }}" alt="Contact illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>

<section class="ec-wrap py-20">
    <div class="mx-auto grid max-w-2xl gap-4">
        <a href="mailto:hello@eurocross-ai.kadetech.online" class="ec-tile flex items-center justify-between !py-5">
            <span class="flex items-center gap-4"><span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span><span><span class="block text-[10px] font-semibold uppercase tracking-widest text-plum-900/40">Email</span><span class="mt-1 block text-sm font-medium text-plum-900">hello@eurocross-ai.kadetech.online</span></span></span>
            <i class="fa-solid fa-arrow-right text-rose-500" aria-hidden="true"></i>
        </a>
        <a href="{{ route('faq') }}" class="ec-tile flex items-center justify-between !py-5">
            <span class="flex items-center gap-4"><span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span><span><span class="block text-[10px] font-semibold uppercase tracking-widest text-plum-900/40">Self help</span><span class="mt-1 block text-sm font-medium text-plum-900">Browse common questions</span></span></span>
            <i class="fa-solid fa-arrow-right text-rose-500" aria-hidden="true"></i>
        </a>
    </div>
</section>

<section class="bg-plum-950 py-20 text-white">
    <div class="ec-wrap text-center">
        <h2 class="ec-display text-3xl sm:text-4xl">Ready to go global?</h2>
        <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action !bg-rose-500 mt-8">Start free trial</a>
    </div>
</section>


<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Ways to reach us</span>
            <h2 class="ec-display mt-4 text-3xl">We're here to help.</h2>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['fa-envelope','General questions','About features, pricing or getting started.'],
                ['fa-file-invoice','Billing & VAT','Questions about subscriptions, invoices or OSS reports.'],
                ['fa-plug','Technical help','API keys, plugin setup and integrations.'],
            ] as $item)
                <article class="ec-tile">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $item[0] }}" aria-hidden="true"></i></span>
                    <h3 class="mt-5 text-base font-semibold text-plum-900">{{ $item[1] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $item[2] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-rose-50 py-20">
    <div class="ec-wrap grid gap-12 lg:grid-cols-2">
        <div class="rounded-3xl bg-white p-8"><img src="{{ asset('images/illustrations/testimonials.svg') }}" alt="Support illustration" class="mx-auto max-h-80 object-contain"></div>
        <div>
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Before you write</span>
            <h2 class="ec-display mt-4 text-3xl">How we can help faster.</h2>
            <p class="ec-lead mt-3">A few details up front help us give you a useful answer right away.</p>
            <ul class="mt-6 space-y-3 text-sm text-plum-900/60">
                @foreach (['Tell us which plan you are on','Include the languages you are using','Describe what you expected to happen'] as $item)
                    <li class="flex items-center gap-2"><i class="fa-solid fa-check text-rose-500" aria-hidden="true"></i>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

@endsection
