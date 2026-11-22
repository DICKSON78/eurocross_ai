@extends('layouts.app')

@section('title', 'Privacy Policy | EuroCross AI')
@section('description', 'Privacy Policy for EuroCross AI.')

@section('content')
<section class="relative overflow-hidden pt-16 pb-12 bg-cream sm:pt-24">
    <div class="ec-wrap relative">
        <span class="ec-kicker"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Legal</span>
        <h1 class="ec-display mt-6 text-4xl sm:text-5xl">Privacy Policy<span class="text-rose-500">.</span></h1>
        <p class="ec-lead mt-5 max-w-2xl">Last updated: {{ now()->format('F j, Y') }}</p>
    </div>
</section>

<section class="ec-wrap py-16">
    <div class="prose prose-plum max-w-3xl">
        <h2>1. Who we are</h2>
        <p>EuroCross AI provides a WordPress/WooCommerce plugin and SaaS service for AI translation and related tools. Contact: <a href="mailto:hello@eurocross-ai.kadetech.online">hello@eurocross-ai.kadetech.online</a>.</p>

        <h2>2. Data we process</h2>
        <ul>
            <li><strong>Account data:</strong> name, email, authentication identifiers, billing-related metadata from payment provider.</li>
            <li><strong>Connected site data:</strong> site URL/domain, site secret/API key identifiers, plan and connection status.</li>
            <li><strong>Translation data:</strong> text/content submitted for translation, source/target language, translation results, usage/quota counts.</li>
            <li><strong>VAT/chat data:</strong> VAT numbers/countries submitted for validation and chat messages submitted to the chatbot, when those features are used.</li>
            <li><strong>Technical/log data:</strong> request metadata, webhook events, error/audit logs needed for security and operations.</li>
        </ul>

        <h2>3. How we use data</h2>
        <p>We use data to provide the service, authenticate connected sites, enforce plans/quotas/access, process billing status, prevent abuse, troubleshoot issues, and improve reliability.</p>

        <h2>4. Third parties</h2>
        <p>We use third-party providers as needed to operate the service, including payment processing (Gumroad) and AI/translation infrastructure configured by the service. These providers process data according to their own terms and privacy policies.</p>

        <h2>5. WordPress plugin data</h2>
        <p>The EuroCross AI WordPress plugin stores settings, cached translations, connection status, and related plugin data in the WordPress database. API keys/secrets are stored server-side in WordPress options. Plugin uninstall removes plugin data as described in the plugin readme.</p>

        <h2>6. Retention and security</h2>
        <p>We retain data only as needed for service operation, legal obligations, and legitimate business purposes. We use reasonable technical and organizational measures to protect data, but no system is 100% secure.</p>

        <h2>7. Your rights and contact</h2>
        <p>Depending on your location, you may have rights to access, correct, delete, or restrict processing of personal data. Contact us at <a href="mailto:hello@eurocross-ai.kadetech.online">hello@eurocross-ai.kadetech.online</a>.</p>
    </div>
</section>
@endsection
