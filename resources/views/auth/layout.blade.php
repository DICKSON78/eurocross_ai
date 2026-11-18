<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#7C0E44">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Sign in') — EuroCross</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@fonts @vite(['resources/css/app.css','resources/js/auth.js'])
</head>
<body class="min-h-screen bg-cream px-5 py-8 sm:px-8 lg:px-10"
      data-firebase-api-key="{{ config('services.firebase.api_key') }}"
      data-firebase-auth-domain="{{ config('services.firebase.auth_domain') }}"
      data-firebase-project-id="{{ config('services.firebase.project_id') }}"
      data-firebase-storage-bucket="{{ config('services.firebase.storage_bucket') }}"
      data-firebase-messaging-sender-id="{{ config('services.firebase.messaging_sender_id') }}"
      data-firebase-app-id="{{ config('services.firebase.app_id') }}"
      data-firebase-measurement-id="{{ config('services.firebase.measurement_id') }}">
<div class="page-enter mx-auto grid min-h-[calc(100vh-4rem)] w-full max-w-7xl items-center gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16">
    <aside class="relative hidden self-stretch overflow-hidden rounded-[2rem] bg-plum-950 px-9 py-10 text-white shadow-lift lg:flex lg:flex-col lg:justify-between">
        <div class="pointer-events-none absolute inset-0 ec-glow opacity-70" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 ec-grain opacity-20" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-rose-500/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-white">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-rose-500 text-white"><i class="fa-solid fa-language text-xl" aria-hidden="true"></i></span>
                <span class="text-lg font-bold tracking-tight">EuroCross</span>
            </a>

            <p class="mt-10 text-xs font-semibold uppercase tracking-[0.22em] text-rose-400">Ready when you are</p>
            <h2 class="ec-display mt-4 text-4xl tracking-tight text-white sm:text-5xl">
                Give your store a<br><span class="text-rose-400">bigger world.</span>
            </h2>
            <p class="mt-5 max-w-lg text-sm leading-7 text-white/60 sm:text-base">Start your 7-day free trial. Translation, SEO and VAT for WooCommerce are waiting.</p>

            <a href="{{ config('payment.gumroad.checkout_starter') }}" class="ec-action mt-7 !bg-rose-500 hover:!bg-rose-400">
                Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
            </a>

            <ul class="mt-8 grid gap-3 text-sm text-white/70">
                @foreach ([
                    ['fa-language', 'Plan-aware languages'],
                    ['fa-file-invoice', 'VAT and OSS built in'],
                    ['fa-robot', 'Optional AI chatbot'],
                ] as $feature)
                    <li class="flex items-center gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white/10 text-rose-400"><i class="fa-solid {{ $feature[0] }}" aria-hidden="true"></i></span>
                        <span>{{ $feature[1] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="relative mt-10 rounded-3xl bg-white/10 p-5 ring-1 ring-white/10 backdrop-blur-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-rose-400">Sell in every language</p>
            <p class="mt-1 text-sm text-white/60">Stay compliant everywhere.</p>
            <img src="{{ asset('images/illustrations/around-the-world.svg') }}" alt="Global reach illustration" class="mx-auto mt-4 w-full max-w-[17rem] object-contain">
        </div>
    </aside>

    <main class="mx-auto w-full max-w-md self-center">
        <div class="rounded-3xl border border-plum-900/8 bg-white p-7 shadow-lift sm:p-9">
            <div class="mb-7 flex items-center justify-between">
                <a href="{{ route('home') }}" class="grid h-11 w-11 place-items-center rounded-2xl bg-ec-primary text-white lg:hidden" aria-label="EuroCross home"><i class="fa-solid fa-language" aria-hidden="true"></i></a>
                <span class="hidden text-sm font-bold tracking-tight text-plum-900 lg:block">EuroCross</span>
                <a href="{{ route('home') }}" class="text-xs font-semibold text-ec-primary hover:underline">Back to site</a>
            </div>

            <div class="mb-6">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[3px] text-ec-primary">@yield('eyebrow','Welcome')</p>
                <h1 class="text-2xl font-black tracking-tight text-plum-900">@yield('heading','Sign in to your account')</h1>
                <p class="mt-2 text-sm leading-6 text-plum-900/50">@yield('subtitle','Access your translation workspace.')</p>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><p class="text-sm font-medium text-emerald-700">{{ session('status') }}</p></div>
            @endif
            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4"><p class="text-sm font-medium text-plum-700">{{ $errors->first() }}</p></div>
            @endif

            @yield('content')
        </div>

        <p class="mt-6 border-t border-plum-900/6 pt-5 text-center text-sm text-plum-900/50">@yield('footer', 'Translation & compliance workspace')</p>
    </main>
</div>
</body>
</html>
