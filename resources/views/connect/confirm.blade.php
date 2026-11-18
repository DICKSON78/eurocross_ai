@extends('auth.layout')

@section('title','Connect WordPress')
@section('eyebrow','WordPress connection')
@section('heading','Connect this store')
@section('subtitle','Review the site details before sharing your EuroCross account.')

@section('content')
<div class="mb-6 rounded-2xl border border-plum-900/6 bg-plum-900/[0.02] p-4">
    <p class="section-label mb-3">You are connecting</p>
    <dl class="space-y-3 text-sm">
        <div class="flex items-start justify-between gap-4">
            <dt class="text-plum-900/50">Site</dt>
            <dd class="max-w-[65%] break-all text-right font-semibold text-plum-900">{{ $connection['site_name'] }}</dd>
        </div>
        <div class="flex items-start justify-between gap-4">
            <dt class="text-plum-900/50">URL</dt>
            <dd class="max-w-[65%] break-all text-right font-medium text-plum-900">{{ $connection['site_url'] }}</dd>
        </div>
        <div class="flex items-start justify-between gap-4">
            <dt class="text-plum-900/50">Account</dt>
            <dd class="max-w-[65%] break-all text-right font-medium text-plum-900">{{ $user->email }}</dd>
        </div>
    </dl>
</div>

<form method="POST" action="{{ route('connect.approve') }}" class="space-y-4">
    @csrf
    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-plum-900/8 p-4">
        <input type="checkbox" name="confirm" value="1" required class="mt-0.5 h-4 w-4 rounded border-plum-900/20 text-ec-primary focus:ring-ec-primary/30">
        <span class="text-sm leading-6 text-plum-900/60">I confirm that I own this WordPress site and want to link its plan and languages to this EuroCross account.</span>
    </label>
    <button type="submit" class="portal-btn w-full">Connect site <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></button>
</form>

<p class="mt-4 text-center text-xs leading-5 text-plum-900/40">The API key is exchanged server-to-server and stored only in your WordPress installation. This approval expires in 10 minutes.</p>
@endsection

@section('footer')
Need a different account? Close this window and start again from your WordPress admin.
@endsection
