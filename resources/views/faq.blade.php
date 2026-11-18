@extends('layouts.app')

@section('title', 'FAQ | EuroCross')
@section('description', 'EuroCross frequently asked questions about trials, languages, billing, VAT and plan changes.')

@section('content')

<section class="relative overflow-hidden pt-16 pb-20 bg-cream sm:pt-24">
    <div class="pointer-events-none absolute inset-0 ec-grain opacity-40" aria-hidden="true"></div>
    <div class="ec-wrap relative grid items-center gap-12 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> FAQ</span>
            <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Good questions.<br><span class="text-rose-500">Clear answers.</span></h1>
            <p class="ec-lead mt-5 max-w-xl">Everything you need to know about EuroCross.</p>
        </div>
        <div class="rounded-3xl border border-plum-900/6 bg-white/75 p-6 shadow-lift backdrop-blur-sm">
            <img src="{{ asset('images/illustrations/questions.svg') }}" alt="Questions illustration" class="mx-auto w-full max-w-sm object-contain">
        </div>
    </div>
</section>

<section class="ec-wrap py-20">
    <div class="mx-auto grid max-w-3xl gap-4">
        @foreach ([
            ['How does the free trial work?','Every plan includes 7 days free. No credit card needed to start.'],
            ['Which languages are supported?','English, French, German, Spanish, Italian, Portuguese, Dutch, Arabic, Chinese and Japanese.'],
            ['How does billing work?','Monthly subscriptions through Gumroad. Cancel anytime from your Gumroad account.'],
            ['Is VAT handled?','Yes — 28 EU VAT rates, VIES validation and OSS reports are included.'],
            ['Can I change plans?','Yes. Cancel your current plan and subscribe to another; access continues until period end.'],
            ['Do I need technical skills?','No. Install the plugin, add your key, and EuroCross takes care of the rest.'],
        ] as $faq)
        <div class="ec-tile">
            <h2 class="font-semibold text-plum-900"><i class="fa-solid fa-circle-question mr-2 text-rose-500" aria-hidden="true"></i>{{ $faq[0] }}</h2>
            <p class="mt-2 text-sm leading-6 text-plum-900/55">{{ $faq[1] }}</p>
        </div>
        @endforeach
    </div>
</section>

<section class="bg-rose-50 py-16 text-center">
    <div class="ec-wrap"><h2 class="ec-display text-3xl">Still curious?</h2><a href="{{ route('contact') }}" class="ec-action mt-6">Get in touch</a></div>
</section>


<section class="border-y border-plum-900/5 bg-white py-20">
    <div class="ec-wrap">
        <div class="max-w-xl">
            <span class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-500">Good to know</span>
            <h2 class="ec-display mt-4 text-3xl">A few more details.</h2>
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-2">
            @foreach ([
                ['fa-language','Can I edit translations?','Yes. Every translation can be reviewed and edited before it goes live.'],
                ['fa-gauge-high','What happens at the rate limit?','Requests are throttled with clear headers so you know when to retry.'],
                ['fa-file-invoice','Where do I find OSS reports?','Generate them any time from the VAT section of your dashboard.'],
                ['fa-key','How secure are my keys?','API keys are hashed at rest and never shown again after creation.'],
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

<section class="ec-wrap py-20 text-center">
    <h2 class="ec-display text-3xl">Still have a question?</h2>
    <p class="ec-lead mx-auto mt-3 max-w-lg">Our team is happy to help you get started.</p>
    <a href="{{ route('contact') }}" class="ec-action mt-6">Contact us</a>
</section>

@endsection
