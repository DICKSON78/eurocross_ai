@extends('auth.layout')

@section('title','Create account')
@section('eyebrow','Get started')
@section('heading','Create your account')
@section('subtitle','Start your 7-day free trial. No card required.')

@section('content')
<button type="button" data-google-button onclick="ecGoogleSignIn()" class="flex w-full items-center justify-center gap-3 rounded-xl border border-plum-900/10 bg-white px-5 py-2.5 text-sm font-semibold text-plum-900 transition hover:bg-plum-900/5">
    @include('auth.partials.google-icon') <span data-google-label>Continue with Google</span>
</button>
<div class="my-6 flex items-center gap-3"><span class="h-px flex-1 bg-plum-900/8"></span><span class="text-[11px] font-semibold uppercase tracking-wider text-plum-900/35">or create with email</span><span class="h-px flex-1 bg-plum-900/8"></span></div>

<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-plum-900">Full name</label>
        <div class="relative"><i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i><input id="name" name="name" value="{{ old('name') }}" required placeholder="Your name" class="portal-input !pl-11"></div>
    </div>
    <div>
        <label for="email" class="mb-1.5 block text-sm font-semibold text-plum-900">Email address</label>
        <div class="relative"><i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i><input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="you@company.com" class="portal-input !pl-11"></div>
    </div>
    <div>
        <label for="password" class="mb-1.5 block text-sm font-semibold text-plum-900">Password</label>
        <div class="relative"><i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i><input id="password" name="password" type="password" required minlength="8" placeholder="At least 8 characters" class="portal-input !pl-11"></div>
    </div>
    <div>
        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-plum-900">Confirm password</label>
        <div class="relative"><i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-plum-900/30" aria-hidden="true"></i><input id="password_confirmation" name="password_confirmation" type="password" required placeholder="••••••••" class="portal-input !pl-11"></div>
    </div>
    <button type="submit" class="w-full rounded-xl bg-ec-primary px-5 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-ec-dark hover:shadow-lg">Create account</button>
</form>
@endsection

@section('footer')
Already have an account? <a href="{{ route('login') }}" class="font-semibold text-ec-primary hover:underline">Sign in</a>
@endsection
