<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $ecTitle = trim($__env->yieldContent('title')) ?: 'EuroCross | Translate Your Store, Grow Everywhere';
        $ecDesc = trim($__env->yieldContent('description')) ?: 'EuroCross translates your WooCommerce store into 15 languages with SEO and VAT compliance built in.';
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $ecTitle }}</title>
    <meta name="description" content="{{ $ecDesc }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="theme-color" content="#7C0E44">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="EuroCross">
    <meta property="og:title" content="{{ $ecTitle }}">
    <meta property="og:description" content="{{ $ecDesc }}">
    <meta property="og:image" content="{{ asset('images/og.jpg') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'EuroCross',
        'description' => $ecDesc,
        'url' => url()->current(),
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web, WooCommerce',
        'offers' => ['@type' => 'Offer', 'price' => '12', 'priceCurrency' => 'EUR'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @fonts
    @if (config('services.google.client_id'))
        <script src="https://accounts.google.com/gsi/client" defer></script>
    @endif
    @vite(['resources/css/app.css', 'resources/js/marketing.js', 'resources/js/auth-popup.js'])
    @stack('head')
</head>
<body class="overflow-x-hidden" @include('layouts.partials.google-auth-data')>

<a href="#ec-main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-plum-700 focus:px-4 focus:py-2 focus:text-white">Skip to content</a>

<header class="sticky top-0 z-50 border-b border-plum-900/5 bg-cream/90 backdrop-blur-xl">
    <div class="ec-wrap flex h-18 min-h-[4.5rem] items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="EuroCross home">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-plum-700 text-white shadow-md shadow-plum-900/20">
                <i class="fa-solid fa-language text-lg" aria-hidden="true"></i>
            </span>
            <span class="text-lg font-bold tracking-tight text-plum-900">EuroCross</span>
        </a>

        <nav class="hidden items-center gap-7 lg:flex" aria-label="Main navigation">
            @foreach ([['home','Home'],['features','Features'],['integrations','Integrations'],['pricing','Pricing'],['about','About'],['faq','FAQ'],['contact','Contact']] as $item)
                <a href="{{ route($item[0]) }}" class="relative py-2 text-sm font-medium text-plum-900/65 transition hover:text-rose-500 {{ request()->routeIs($item[0]) ? '!text-rose-500 after:absolute after:inset-x-0 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-rose-500' : '' }}">{{ $item[1] }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-3 lg:flex" data-header-actions>
            @auth
                @php $ecHeaderUser = auth()->user(); @endphp
                <a href="{{ route('dashboard') }}" data-account-dashboard class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-3 transition hover:bg-plum-900/5" aria-label="Open dashboard for {{ $ecHeaderUser->name }}">
                    @if ($ecHeaderUser->avatarUrl())
                        <img src="{{ $ecHeaderUser->avatarUrl() }}" alt="" class="h-9 w-9 rounded-full border-2 border-rose-200 object-cover">
                    @else
                        <span class="grid h-9 w-9 place-items-center rounded-full border-2 border-rose-200 bg-ec-primary text-xs font-bold text-white">{{ strtoupper(substr($ecHeaderUser->name, 0, 1)) }}</span>
                    @endif
                    <span class="hidden max-w-32 truncate text-sm font-semibold text-plum-900 xl:block">{{ $ecHeaderUser->name }}</span>
                    <span class="text-sm font-semibold text-plum-700">Dashboard</span>
                    <i class="fa-solid fa-arrow-right text-xs text-plum-900/40" aria-hidden="true"></i>
                </a>
            @else
                <a href="{{ route('login') }}" data-guest-sign-in class="text-sm font-medium text-plum-900/65 hover:text-rose-500">Sign in</a>
                <a href="{{ config('payment.gumroad.checkout_starter') }}" data-guest-trial class="ec-action !min-h-10 !rounded-lg !px-5 !py-2">Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
            @endauth
        </div>

        <button type="button" data-ec-menu-open class="grid h-10 w-10 place-items-center rounded-lg border border-plum-900/10 text-plum-900 lg:hidden" aria-label="Open menu" aria-expanded="false">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</header>

<div data-ec-menu class="fixed inset-0 z-[60] hidden lg:hidden" aria-hidden="true">
    <div data-ec-menu-close class="absolute inset-0 bg-plum-950/50 backdrop-blur-sm"></div>
    <div class="relative ml-auto h-full w-[86%] max-w-sm overflow-y-auto bg-cream p-6 shadow-2xl">
        <div class="flex items-center justify-between">
            <span class="text-lg font-bold text-plum-900">Menu</span>
            <button type="button" data-ec-menu-close class="grid h-10 w-10 place-items-center rounded-lg border border-plum-900/10" aria-label="Close menu"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <nav class="mt-8 grid gap-2" aria-label="Mobile navigation">
            @foreach ([['home','Home','fa-house'],['features','Features','fa-wand-magic-sparkles'],['integrations','Integrations','fa-plug'],['pricing','Pricing','fa-tag'],['about','About','fa-circle-info'],['faq','FAQ','fa-circle-question'],['contact','Contact','fa-envelope']] as $item)
                <a href="{{ route($item[0]) }}" class="flex items-center gap-3 rounded-xl border border-plum-900/5 bg-white px-4 py-3 text-sm font-medium text-plum-900 {{ request()->routeIs($item[0]) ? '!border-rose-200 !bg-rose-50 !text-rose-500' : '' }}">
                    <i class="fa-solid {{ $item[2] }} w-5 text-plum-600" aria-hidden="true"></i>{{ $item[1] }}
                </a>
            @endforeach
        </nav>
        @auth
            @php $ecMenuUser = auth()->user(); @endphp
            <div class="mt-6 rounded-2xl border border-plum-900/5 bg-white p-4" data-account-menu>
                <div class="flex items-center gap-3">
                    @if ($ecMenuUser->avatarUrl())
                        <img src="{{ $ecMenuUser->avatarUrl() }}" alt="" class="h-10 w-10 rounded-full border-2 border-rose-200 object-cover">
                    @else
                        <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-rose-200 bg-ec-primary text-sm font-bold text-white">{{ strtoupper(substr($ecMenuUser->name, 0, 1)) }}</span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-plum-900">{{ $ecMenuUser->name }}</p>
                        <p class="text-xs text-plum-900/45">Signed in</p>
                    </div>
                </div>
                <a href="{{ route('dashboard') }}" data-account-dashboard class="ec-action mt-4 w-full">Dashboard <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="w-full rounded-xl border border-plum-900/10 py-2.5 text-sm font-semibold text-plum-900/60 transition hover:bg-plum-900/5">Log out</button>
                </form>
            </div>
        @else
            <a href="{{ config('payment.gumroad.checkout_starter') }}" data-guest-trial class="ec-action mt-6 w-full">Start free trial <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></a>
            <a href="{{ route('login') }}" data-guest-sign-in class="mt-4 block text-center text-sm font-medium text-plum-900/60">Sign in to dashboard</a>
        @endauth
    </div>
</div>

<main id="ec-main">
    @yield('content')
</main>

<footer class="bg-plum-950 text-white">
    <div class="ec-wrap py-16">
        <div class="grid gap-10 md:grid-cols-[1.3fr_1fr_1fr_1fr]">
            <div>
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-rose-500 text-white"><i class="fa-solid fa-language" aria-hidden="true"></i></span>
                    <span class="text-lg font-bold">EuroCross</span>
                </a>
                <p class="mt-5 max-w-xs text-sm leading-7 text-white/55">Translate your store. Reach new markets. Grow with confidence.</p>
                <div class="mt-6 flex gap-3 text-white/50">
                    <a href="#" class="hover:text-rose-400" aria-label="Facebook"><i class="fa-brands fa-facebook" aria-hidden="true"></i></a>
                    <a href="#" class="hover:text-rose-400" aria-label="Twitter"><i class="fa-brands fa-twitter" aria-hidden="true"></i></a>
                    <a href="#" class="hover:text-rose-400" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
                    <a href="#" class="hover:text-rose-400" aria-label="LinkedIn"><i class="fa-brands fa-linkedin" aria-hidden="true"></i></a>
                </div>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-400">Explore</h2>
                <ul class="mt-5 space-y-3 text-sm text-white/60">
                    <li><a href="{{ route('features') }}" class="hover:text-white">Features</a></li>
                    <li><a href="{{ route('integrations') }}" class="hover:text-white">Integrations</a></li>
                    <li><a href="{{ route('pricing') }}" class="hover:text-white">Pricing</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-white">About</a></li>
                    <li><a href="{{ route('faq') }}" class="hover:text-white">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-400">Product</h2>
                <ul class="mt-5 space-y-3 text-sm text-white/60">
                    <li><a href="{{ route('features') }}" class="hover:text-white">Smart translation</a></li>
                    <li><a href="{{ route('features') }}" class="hover:text-white">Multilingual SEO</a></li>
                    <li><a href="{{ route('features') }}" class="hover:text-white">VAT &amp; OSS</a></li>
                    <li><a href="/dashboard" class="hover:text-white">Dashboard</a></li>
                </ul>
            </div>
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-400">Say hello</h2>
                <ul class="mt-5 space-y-3 text-sm text-white/60">
                    <li><a href="mailto:hello@eurocross-ai.kadetech.online" class="hover:text-white">hello@eurocross-ai.kadetech.online</a></li>
                    <li><a href="{{ config('payment.gumroad.checkout_starter') }}" class="text-rose-400 hover:text-rose-300">Start free trial →</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-12 flex flex-col gap-3 border-t border-white/10 pt-6 text-xs text-white/40 sm:flex-row sm:items-center sm:justify-between">
            <p>© {{ date('Y') }} EuroCross. All rights reserved.</p>
            <p><i class="fa-solid fa-globe mr-1 text-rose-400" aria-hidden="true"></i>15 languages supported</p>
        </div>
    </div>
</footer>

@guest
<div data-auth-popup class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ec-auth-popup-title">
    <div data-auth-popup-close class="absolute inset-0 bg-plum-950/60 backdrop-blur-sm"></div>
    <div class="relative w-full max-w-md rounded-3xl border border-plum-900/8 bg-white p-8 shadow-2xl">
        <button type="button" data-auth-popup-close class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-xl text-plum-900/40 transition hover:bg-plum-900/5 hover:text-plum-900" aria-label="Close sign-in popup"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <div class="grid h-12 w-12 place-items-center rounded-2xl bg-plum-700 text-white"><i class="fa-solid fa-language text-xl" aria-hidden="true"></i></div>
        <p class="mt-6 text-[11px] font-semibold uppercase tracking-[0.22em] text-rose-500">Welcome to EuroCross</p>
        <h2 id="ec-auth-popup-title" class="mt-2 text-2xl font-black tracking-tight text-plum-900">Continue as a EuroCross user</h2>
        <p class="mt-3 text-sm leading-7 text-plum-900/55">Create your free workspace in one tap. Your 7-day trial starts automatically.</p>
        <button type="button" data-auth-popup-google class="mt-7 flex w-full items-center justify-center gap-3 rounded-xl border border-plum-900/10 bg-white px-5 py-3 text-sm font-semibold text-plum-900 transition hover:bg-plum-900/5">
            @include('auth.partials.google-icon') <span data-auth-popup-google-label>Continue with Google</span>
        </button>
        <p data-auth-popup-error hidden class="mt-3 text-sm leading-6 text-rose-600" role="alert" aria-live="polite"></p>
        <div class="mt-5 flex items-center justify-between text-xs text-plum-900/50">
            <a href="{{ route('login') }}" class="font-semibold text-plum-700 hover:text-rose-500">Sign in</a>
            <a href="{{ route('register') }}" class="font-semibold text-plum-700 hover:text-rose-500">Create with email</a>
        </div>
    </div>
</div>
@endguest

</body>
</html>
