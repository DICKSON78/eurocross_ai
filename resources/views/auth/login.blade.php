@extends('auth.layout')

@section('title','Sign in')
@section('eyebrow','Sign in')
@section('heading','Welcome back')
@section('subtitle','Enter your details to access your workspace.')

@section('content')
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="mb-1.5 block text-sm font-semibold text-plum-900">Email address</label>
        <div class="relative">
            <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="you@company.com" class="portal-input !pl-11">
        </div>
    </div>
    <div x-data="{ show: false }">
        <label for="password" class="mb-1.5 block text-sm font-semibold text-plum-900">Password</label>
        <div class="relative">
            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i>
            <input id="password" name="password" required autocomplete="current-password" placeholder="••••••••" :type="show ? 'text' : 'password'" class="portal-input !pl-11 !pr-11">
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-plum-900/30 hover:text-plum-900/60" aria-label="Toggle password visibility">
                <i class="fa-solid fa-eye" x-show="!show" aria-hidden="true"></i>
                <i class="fa-solid fa-eye-slash" x-show="show" x-cloak aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <label class="flex cursor-pointer items-center gap-2 pt-1">
        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-plum-900/20 text-ec-primary focus:ring-ec-primary/30">
        <span class="text-sm text-plum-900/60">Remember me</span>
    </label>
    <button type="submit" class="w-full rounded-xl bg-ec-primary px-5 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-ec-dark hover:shadow-lg">Sign in</button>

    <div class="my-6 flex items-center gap-3"><span class="h-px flex-1 bg-plum-900/8"></span><span class="text-[11px] font-semibold uppercase tracking-wider text-plum-900/35">or</span><span class="h-px flex-1 bg-plum-900/8"></span></div>
    <button type="button" data-google-button onclick="ecGoogleSignIn()" class="flex w-full items-center justify-center gap-3 rounded-xl border border-plum-900/10 bg-white px-5 py-2.5 text-sm font-semibold text-plum-900 transition hover:bg-plum-900/5">
        @include('auth.partials.google-icon') <span data-google-label>Continue with Google</span>
    </button>
    <p data-google-error hidden class="mt-3 text-sm leading-6 text-rose-600" role="alert" aria-live="polite"></p>
</form>
@endsection

@section('footer')
New here? <a href="{{ route('register') }}" class="font-semibold text-ec-primary hover:underline">Create an account</a>
@endsection
