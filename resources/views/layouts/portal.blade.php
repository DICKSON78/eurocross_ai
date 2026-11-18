@php
    $user = auth()->user();
    $settings = $user->euroCrossSettings()->first();
    $nav = [
        ['label' => 'Workspace', 'items' => [
            ['label' => 'Overview', 'icon' => 'fa-gauge-high', 'route' => route('dashboard'), 'id' => 'overview'],
            ['label' => 'Translation', 'icon' => 'fa-language', 'route' => route('dashboard.module','translation'), 'id' => 'translation'],
        ]],
        ['label' => 'Billing & tools', 'items' => [
            ['label' => 'VAT tools', 'icon' => 'fa-file-invoice', 'route' => route('dashboard.module','vat'), 'id' => 'vat'],
            ['label' => 'Billing', 'icon' => 'fa-credit-card', 'route' => route('dashboard.module','billing'), 'id' => 'billing'],
        ]],
        ['label' => 'Tools', 'items' => [
            ['label' => 'API Keys', 'icon' => 'fa-key', 'route' => route('dashboard.module','keys'), 'id' => 'keys'],
            ['label' => 'Chatbot', 'icon' => 'fa-robot', 'route' => route('dashboard.module','chatbot'), 'id' => 'chatbot'],
        ]],
        ['label' => 'Account', 'items' => [
            ['label' => 'Settings', 'icon' => 'fa-gear', 'route' => route('dashboard.module','settings'), 'id' => 'settings'],
        ]],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#7C0E44">
<title>@yield('title','Dashboard') — EuroCross</title>
<link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@fonts @vite(['resources/css/app.css','resources/js/portal.js'])
</head>
<body class="bg-cream text-plum-900 antialiased">
<div x-data="{ sidebarOpen:false, collapsed: localStorage.getItem('ecCollapsed') === 'true' }"
     x-effect="localStorage.setItem('ecCollapsed', collapsed)"
     class="flex h-screen overflow-hidden">

    <div class="fixed inset-0 z-40 bg-plum-950/50 md:hidden" x-show="sidebarOpen" x-cloak @click="sidebarOpen=false"></div>

    <div class="fixed inset-y-0 left-0 z-50 w-[272px] max-w-[85vw] md:hidden" x-show="sidebarOpen" x-cloak>
        <div class="flex h-full w-full flex-col transition-transform duration-300" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" style="background:linear-gradient(180deg,#2A0A1C 0%,#430F2C 55%,#1A0410 100%);">
            @include('layouts.partials.portal-sidebar', ['nav' => $nav, 'collapsible' => false])
        </div>
    </div>

    <aside class="hidden md:flex shrink-0 flex-col transition-[width] duration-300"
           :class="collapsed ? 'w-[76px]' : 'w-[272px]'"
           style="background:linear-gradient(180deg,#2A0A1C 0%,#430F2C 55%,#1A0410 100%);">
        @include('layouts.partials.portal-sidebar', ['nav' => $nav, 'collapsible' => true])
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-plum-900/6 bg-white px-3 md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="rounded-lg p-2 text-plum-900/50 hover:bg-plum-900/5 md:hidden" @click="sidebarOpen=true" aria-label="Open menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
                <div class="hidden items-center gap-3 text-sm text-plum-900/45 sm:flex">
                    <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-calendar text-[13px] text-ec-primary" aria-hidden="true"></i>{{ now()->format('l, d F Y') }}</span>
                    <span class="inline-flex items-center gap-1.5"><i class="fa-solid fa-clock text-[13px] text-ec-primary" aria-hidden="true"></i>{{ now()->format('H:i') }}</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div x-data="{ open:false }" class="relative">
                    <button type="button" @click="open=!open" @click.outside="open=false" class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-3 transition hover:bg-plum-900/5">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="" class="h-9 w-9 rounded-full border-2 border-rose-200 object-cover">
                        @else
                            <span class="grid h-9 w-9 place-items-center rounded-full border-2 border-rose-200 bg-ec-primary text-xs font-bold text-white">{{ strtoupper(substr($user->name,0,1)) }}</span>
                        @endif
                        <span class="hidden text-left leading-tight sm:block"><span class="block text-sm font-semibold text-plum-900">{{ $user->name }}</span><span class="block text-[11px] text-plum-900/40">{{ ucfirst($settings?->plan ?? 'starter') }} plan</span></span>
                        <i class="fa-solid fa-chevron-down text-[12px] text-plum-900/30" aria-hidden="true"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition.origin.top.right class="fade-in absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-plum-900/6 bg-white py-2 shadow-xl">
                        <div class="border-b border-plum-900/6 px-4 py-2"><p class="truncate text-sm font-semibold text-plum-900">{{ $user->name }}</p><p class="truncate text-xs text-plum-900/40">{{ $user->email }}</p></div>
                        <a href="/" class="flex items-center gap-3 px-4 py-2.5 text-sm text-plum-900/60 hover:bg-plum-900/5"><i class="fa-solid fa-house w-4" aria-hidden="true"></i>Website</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-rose-500 hover:bg-rose-50"><i class="fa-solid fa-right-from-bracket w-4" aria-hidden="true"></i>Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto bg-cream p-4 md:p-6" id="app-main">
            <div id="app-flash-container">
                @if (session('status'))<div class="fade-in mb-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><i class="fa-solid fa-check mt-0.5" aria-hidden="true"></i><span>{{ session('status') }}</span></div>@endif
                @if ($errors->any())
                    <div class="fade-in mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-plum-700"><p class="mb-1 font-semibold">Please fix the following:</p><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
            </div>
            <div id="app-content" class="page-enter">@yield('content')</div>
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
