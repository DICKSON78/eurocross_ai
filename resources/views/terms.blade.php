@extends('layouts.app')

@section('title', 'Terms of Service | EuroCross AI')
@section('description', 'Terms of Service for EuroCross AI.')

@section('content')
<section class="relative overflow-hidden pt-16 pb-12 bg-cream sm:pt-24">
    <div class="ec-wrap relative">
        <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Legal</span>
        <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Terms of Service<span class="text-rose-500">.</span></h1>
        <p class="ec-lead mt-5 max-w-2xl">Last updated: {{ now()->format('F j, Y') }}</p>
    </div>
</section>

<section class="ec-wrap py-16">
    <div class="prose prose-plum max-w-3xl">
        <h2>1. Service</h2>
        <p>EuroCross AI provides a WordPress/WooCommerce plugin and SaaS API for AI-powered translation, language switching, and optional VAT/chatbot tools. The service is provided by EuroCross AI via <a href="https://eurocross-ai.kadetech.online">eurocross-ai.kadetech.online</a>.</p>

        <h2>2. Accounts and access</h2>
        <p>You must provide accurate account information and keep your API keys and credentials secure. You are responsible for activity under your account and connected WordPress sites.</p>

        <h2>3. Plans, trials, and billing</h2>
        <p>EuroCross AI may offer Free, trial, and paid plans. Plan limits (words, languages, rate limits, features) are described on the pricing page and in your dashboard. Paid subscriptions are processed by Gumroad as Merchant of Record unless otherwise stated. Taxes, invoices, and refunds are handled according to the payment provider terms and applicable law.</p>

        <h2>4. Acceptable use</h2>
        <p>You may not abuse the service, attempt to bypass quotas/access controls, reverse engineer protected service components, send unlawful content, or use the service in a way that harms others or the platform.</p>

        <h2>5. External service and data</h2>
        <p>The WordPress plugin sends data to the EuroCross AI API when you use connected features (translations, VAT checks, chatbot, connection/sync). See our <a href="{{ route('privacy') }}">Privacy Policy</a> for details.</p>

        <h2>6. Availability and changes</h2>
        <p>We aim for reliable service but do not guarantee uninterrupted availability. We may update features, limits, or these terms. Material changes will be posted on this page.</p>

        <h2>7. Disclaimer</h2>
        <p>The service is provided "as is" to the extent permitted by law. VAT/OSS outputs are informational only and are not tax or legal advice. You are responsible for verifying translations and compliance before relying on them.</p>

        <h2>8. Contact</h2>
        <p>For questions, contact <a href="mailto:hello@eurocross-ai.kadetech.online">hello@eurocross-ai.kadetech.online</a> or use the <a href="{{ route('contact') }}">contact page</a>.</p>
    </div>
</section>
@endsection
