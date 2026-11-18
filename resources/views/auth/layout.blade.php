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
<div class="page-enter mx-auto grid w-full max-w-6xl items-center gap-12 lg:grid-cols-2 lg:gap-20">
    <aside class="hidden lg:block">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-plum-900">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-ec-primary text-white"><i class="fa-solid fa-language text-xl" aria-hidden="true"></i></span>
            <span class="text-lg font-bold tracking-tight">EuroCross</span>
        </a>

        <p class="ec-kicker mt-10">Translation, SEO and VAT for WooCommerce</p>
        <h2 class="ec-display mt-6 text-4xl tracking-tight text-plum-900 sm:text-5xl">
            Sell in every language.<br><span class="text-plum-600">Stay compliant everywhere.</span>
        </h2>
        <p class="ec-lead mt-6 max-w-xl">One workspace for AI translations, plan-based language controls, and EU VAT/OSS rules. Connect your store in minutes.</p>

        <ul class="mt-10 space-y-4">
            @foreach ([
                ['fa-language', 'Plan-aware languages', 'Offer only the languages included in your package.'],
                ['fa-file-invoice', 'VAT and OSS built in', 'Validate VAT numbers and apply the right EU rules.'],
                ['fa-robot', 'Optional AI chatbot', 'Answer customer questions in their own language.'],
            ] as $feature)
                <li class="flex items-start gap-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-500"><i class="fa-solid {{ $feature[0] }}" aria-hidden="true"></i></span>
                    <span><strong class="block text-sm text-plum-900">{{ $feature[1] }}</strong><span class="text-sm text-plum-900/50">{{ $feature[2] }}</span></span>
                </li>
            @endforeach
        </ul>
    </aside>

    <main class="mx-auto w-full max-w-md">
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
