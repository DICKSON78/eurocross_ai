@extends('layouts.portal')

@section('title', ucfirst($module))

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div class="flex items-center gap-3">
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-ec-primary" style="background:color-mix(in srgb,#E82757 12%,white);">
            <i class="fa-solid {{ $modules[$module][0] ?? 'fa-gauge-high' }} text-ec-primary" aria-hidden="true"></i>
        </div>
        <div>
            <h1 class="page-title">{{ $modules[$module][1] ?? 'Overview' }}</h1>
            <p class="page-subtitle mt-0.5">
                @switch($module)
                    @case('translation') Track your translation activity and usage @break
                    @case('vat') View optional VAT settings, validation status and informational OSS notes @break
                    @case('billing') Your plan, subscription and payment history @break
                    @case('keys') Manage the keys connected to your store @break
                    @case('chatbot') Review conversations from your store chatbot @break
                    @case('settings') Your account and store configuration @break
                    @default Your workspace at a glance @break
                @endswitch
            </p>
        </div>
    </div>
    @if ($module === 'billing')
        <a href="{{ route('billing.checkout', $settings->plan) }}" class="portal-btn" style="background:#7C0E44;">Recharge {{ ucfirst($settings->plan) }} plan <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a>
    @endif
</div>

<div class="mt-6 space-y-6">
    @if ($module === 'overview')
        <div class="grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 sm:grid-cols-4">
            <div class="stat-card"><div class="flex items-start justify-between"><div><p class="section-label mb-1.5">Current plan</p><p class="text-2xl font-bold text-plum-900">{{ ucfirst($settings->plan) }}</p></div><span class="stat-icon"><i class="fa-solid fa-tag" aria-hidden="true"></i></span></div></div>
            <div class="stat-card"><div class="flex items-start justify-between"><div><p class="section-label mb-1.5">Words this month</p><p class="text-2xl font-bold text-plum-900">{{ number_format($wordsUsed) }}</p><p class="mt-0.5 text-[11px] text-plum-900/40">of {{ number_format($wordsLimit) }}</p></div><span class="stat-icon"><i class="fa-solid fa-language" aria-hidden="true"></i></span></div></div>
            <div class="stat-card"><div class="flex items-start justify-between"><div><p class="section-label mb-1.5">Access status</p><p class="text-xl font-bold capitalize text-plum-900">{{ $access['status'] }}</p></div><span class="stat-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span></div></div>
            <div class="stat-card"><div class="flex items-start justify-between"><div><p class="section-label mb-1.5">Strings translated</p><p class="text-2xl font-bold text-plum-900">{{ number_format($usage->strings ?? 0) }}</p></div><span class="stat-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></span></div></div>
        </div>
        <div class="table-card">
            <div class="flex items-center justify-between border-b border-plum-900/6 px-6 py-4"><h2 class="text-sm font-bold text-plum-900">Quick actions</h2><span class="text-xs text-plum-900/40">Everything you need</span></div>
            <div class="grid gap-3 p-6 sm:grid-cols-3">
                @foreach ([['translation','Translate content'],['vat','VAT tools'],['keys','API keys']] as $action)
                    <a href="{{ route('dashboard.module',$action[0]) }}" class="flex items-center gap-3 rounded-xl border border-plum-900/6 p-4 text-sm font-medium transition hover:border-rose-500 hover:text-rose-500"><i class="fa-solid fa-arrow-right text-rose-500" aria-hidden="true"></i>{{ $action[1] }}</a>
                @endforeach
            </div>
        </div>
    @elseif ($module === 'translation')
        <div class="table-card">
            <div class="flex items-center justify-between border-b border-plum-900/6 px-6 py-4"><h2 class="text-sm font-bold text-plum-900">Usage history</h2><span class="text-xs text-plum-900/40">Last 30 days</span></div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-plum-900/[0.03]"><tr>
                <th class="table-head">Date</th><th class="table-head">Words</th><th class="table-head">Strings</th><th class="table-head">Tokens</th><th class="table-head">Cost</th>
            </tr></thead><tbody class="divide-y divide-plum-900/5">
                @forelse ($usage as $row)
                    <tr class="hover:bg-plum-900/[0.02]"><td class="px-4 py-3">{{ $row->usage_date?->format('Y-m-d') }}</td><td class="px-4 py-3">{{ number_format($row->words_translated) }}</td><td class="px-4 py-3">{{ number_format($row->strings_translated) }}</td><td class="px-4 py-3">{{ number_format($row->tokens_used) }}</td><td class="px-4 py-3">€{{ number_format($row->ai_cost,4) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-plum-900/40">No translation usage yet.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    @elseif ($module === 'vat')
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="table-card p-6">
                <h2 class="text-sm font-bold text-plum-900">VAT settings</h2>
                <dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><dt class="text-plum-900/50">Default country</dt><dd class="font-semibold">{{ $vatSettings->default_country }}</dd></div><div class="flex justify-between"><dt class="text-plum-900/50">OSS</dt><dd>{{ $vatSettings->use_oss ? 'Enabled' : 'Disabled' }}</dd></div><div class="flex justify-between"><dt class="text-plum-900/50">VIES status</dt><dd class="capitalize">{{ $vatSettings->viies_status }}</dd></div></dl>
            </div>
            <div class="table-card p-6"><h2 class="text-sm font-bold text-plum-900">Recent transactions</h2><p class="mt-3 text-sm text-plum-900/50">{{ $transactions->count() }} records found.</p></div>
        </div>
    @elseif ($module === 'billing')
        @php
            $access = $settings->accessStatus();
        @endphp
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="table-card p-6">
                <h2 class="text-sm font-bold text-plum-900">Subscription</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div><p class="section-label">Plan</p><p class="mt-1 font-semibold">{{ ucfirst($settings->plan) }}</p></div>
                    <div><p class="section-label">Access</p><p class="mt-1 font-semibold capitalize">{{ $access['status'] ?? ($settings->subscription_status ?? 'inactive') }}</p></div>
                    <div><p class="section-label">Billing email</p><p class="mt-1">{{ $settings->billing_email ?? $user->email }}</p></div>
                    <div><p class="section-label">Renews / expires</p><p class="mt-1">{{ $settings->subscription_current_period_end?->format('Y-m-d') ?? $settings->trial_expires?->format('Y-m-d') ?? '—' }}</p></div>
                </div>
                @if (! $settings->hasActiveAccess())
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        Your free trial or subscription is not active. Choose a plan below to recharge. After payment is confirmed, your existing WordPress plugin key will work again automatically. If you don't have a key yet, generate one in <a href="{{ route('dashboard.module', 'keys') }}" class="font-semibold underline">API Keys</a>.
                    </div>
                @else
                    <p class="mt-5 text-sm text-plum-900/60">After payment, activation usually takes about a minute. Your existing plugin API key continues to work when your account is active.</p>
                @endif
            </div>
            <div class="table-card p-6">
                <h2 class="text-sm font-bold text-plum-900">Recharge or change plan</h2>
                <p class="mt-2 text-sm text-plum-900/60">Sign-in is required before payment so your subscription and API key are linked to this account.</p>
                <div class="mt-4 space-y-3">
                    @foreach (['starter' => 'Starter — €12/month', 'growth' => 'Growth — €25/month', 'scale' => 'Scale — €65/month'] as $planKey => $label)
                        <a href="{{ route('billing.checkout', $planKey) }}" class="flex items-center justify-between rounded-xl border border-plum-900/8 px-4 py-3 text-sm font-medium transition hover:border-rose-500 hover:text-rose-500">
                            <span>{{ $label }}</span>
                            <span class="text-rose-500">Pay securely <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i></span>
                        </a>
                    @endforeach
                </div>
                <p class="mt-4 text-xs text-plum-900/40">Secure checkout is processed by Gumroad. We never store card details.</p>
            </div>
        </div>
        <div class="table-card">
            <div class="flex items-center justify-between border-b border-plum-900/6 px-6 py-4"><h2 class="text-sm font-bold text-plum-900">Payment history</h2><span class="text-xs text-plum-900/40">Recent transactions</span></div>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-plum-900/[0.03]"><tr>
                <th class="table-head">Date</th><th class="table-head">Amount</th><th class="table-head">Status</th><th class="table-head">Reference</th>
            </tr></thead><tbody class="divide-y divide-plum-900/5">
                @forelse ($transactions as $tx)
                    <tr class="hover:bg-plum-900/[0.02]"><td class="px-4 py-3">{{ $tx->created_at?->format('Y-m-d H:i') }}</td><td class="px-4 py-3">{{ $tx->currency ?? 'EUR' }} {{ number_format((float) ($tx->amount ?? 0), 2) }}</td><td class="px-4 py-3 capitalize">{{ $tx->status ?? '—' }}</td><td class="px-4 py-3 text-xs text-plum-900/50">{{ $tx->provider_payment_id ?? $tx->id }}</td></tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-plum-900/40">No payments recorded yet.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    @elseif ($module === 'keys')
        @if (session('new_api_key'))
            <div class="fade-in mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                <p class="text-sm font-bold text-emerald-800">Copy this key now — it will not be shown again.</p>
                <code class="mt-3 block break-all rounded-xl bg-white p-4 text-xs text-plum-900 ring-1 ring-emerald-200">{{ session('new_api_key') }}</code>
                <p class="mt-3 text-xs text-emerald-700">Paste it into the EuroCross AI plugin settings in WordPress (Connection → API key), or use Connect with EuroCross. After recharge/payment is activated, the same key will work again.</p>
            </div>
        @endif
        <div class="table-card">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-plum-900/6 px-6 py-4">
                <div><h2 class="text-sm font-bold text-plum-900">API keys & plugin access</h2><p class="mt-1 text-xs text-plum-900/40">Use an <strong>eurocross</strong> key in the WordPress plugin. If you connected your site from WordPress, a key was created automatically.</p></div>
                <form method="POST" action="{{ route('dashboard.keys.generate') }}">
                    @csrf
                    <button type="submit" class="portal-btn !py-2"><i class="fa-solid fa-key" aria-hidden="true"></i> Generate API key</button>
                </form>
            </div>
            <div class="border-b border-plum-900/6 px-6 py-4 space-y-2 text-sm text-plum-900/70">
                <p>1. Install and activate the EuroCross AI plugin in your WordPress (WooCommerce) admin. 2. In plugin settings, click <strong>Connect with EuroCross</strong> (recommended) or paste an API key from here. 3. Start using translations during your 7-day trial. 4. After 7 days, recharge from <a href="{{ route('dashboard.module', 'billing') }}" class="font-semibold text-ec-primary hover:underline">Billing</a> (account required), then keep using the same key.</p>
            </div>
            <div class="divide-y divide-plum-900/5">
                @forelse ($keys as $key)
                    @php
                        $raw = (string) ($key->encrypted_key ?? '');
                        $masked = $raw !== '' ? substr($raw, 0, 12).'…'.substr($raw, -4) : '—';
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                        <div>
                            <p class="text-sm font-medium">{{ $key->key_name ?: ucfirst($key->provider).' key' }} <span class="text-plum-900/40">({{ $key->provider }})</span></p>
                            <p class="mt-1 font-mono text-xs text-plum-900/50">{{ $masked }}</p>
                        </div>
                        <span class="text-xs {{ $key->active ? 'text-emerald-700' : 'text-plum-900/40' }}">{{ $key->active ? 'Active' : 'Inactive' }}</span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-plum-900/40">No keys yet. Connect your WordPress site or generate one above.</p>
                @endforelse
            </div>
        </div>
    @elseif ($module === 'chatbot')
        <div class="table-card">
            <div class="border-b border-plum-900/6 px-6 py-4"><h2 class="text-sm font-bold text-plum-900">Conversations</h2></div>
            <div class="divide-y divide-plum-900/5">
                @forelse ($messages as $message)
                    <div class="px-6 py-4"><p class="text-xs text-plum-900/40">{{ $message->role }} · {{ $message->language }}</p><p class="mt-1 text-sm">{{ $message->message }}</p></div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-plum-900/40">No conversations yet.</p>
                @endforelse
            </div>
        </div>
    @elseif ($module === 'settings')
        <div class="table-card p-6">
            <h2 class="text-sm font-bold text-plum-900">Account</h2>
            <dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><dt class="text-plum-900/50">Name</dt><dd class="font-semibold">{{ $user->name }}</dd></div><div class="flex justify-between"><dt class="text-plum-900/50">Email</dt><dd>{{ $user->email }}</dd></div><div class="flex justify-between"><dt class="text-plum-900/50">Locale</dt><dd>{{ $settings->locale }}</dd></div></dl>
            <p class="mt-6 text-xs text-plum-900/40">Manage your plugin keys, billing, and account from this portal. Generate or copy API keys in <a href="{{ route('dashboard.module', 'keys') }}" class="text-ec-primary hover:underline">API Keys</a>.</p>
        </div>
    @endif
</div>
@endsection
