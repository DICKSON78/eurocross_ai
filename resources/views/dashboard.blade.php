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
                        Your free trial or subscription is not active. Choose a plan below to recharge. After payment is confirmed, your existing WordPress plugin key will work again automatically. If you don't have a key yet, connect your WordPress site or manage keys in <a href="{{ route('dashboard.module', 'keys') }}" class="font-semibold underline">API Keys</a>.
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
        @php
            $access = $settings->accessStatus();
            $plans = [
                'starter' => ['name' => 'Starter', 'price' => '€12', 'words' => '50,000 words / month', 'languages' => '3 languages'],
                'growth' => ['name' => 'Growth', 'price' => '€25', 'words' => '100,000 words / month', 'languages' => '5 languages'],
                'scale' => ['name' => 'Scale', 'price' => '€65', 'words' => '300,000 words / month', 'languages' => '15 languages'],
            ];
        @endphp
        <div x-data="{ buyOpen: {{ $access['allowed'] ? 'false' : 'true' }} }">
            @if (! $access['allowed'])
                <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-bold text-amber-900">Your free trial has ended</p>
                            <p class="mt-1 text-sm text-amber-900/80">Purchase a plan on this website to activate access and get your API key working again. Payment requires a signed-in account and is processed securely by Gumroad.</p>
                        </div>
                        <button type="button" @click="buyOpen = true" class="portal-btn !bg-amber-700 hover:!bg-amber-800 shrink-0"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i> Buy API access</button>
                    </div>
                </div>
            @elseif ($settings->isTrial() && ! $settings->hasActiveSubscription())
                <div class="mb-6 rounded-2xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">
                    <p class="font-bold">Free trial active</p>
                    <p class="mt-1 text-sky-900/80">During your 7-day trial, your API key is provided freely when you connect your WordPress site. Manual key generation is available after purchasing a plan. When your trial ends, you will be redirected here to buy API access.</p>
                </div>
            @endif

            @if (session('new_api_key'))
                <div class="fade-in mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                    <p class="text-sm font-bold text-emerald-800">Copy this key now — it will not be shown again.</p>
                    <code class="mt-3 block break-all rounded-xl bg-white p-4 text-xs text-plum-900 ring-1 ring-emerald-200">{{ session('new_api_key') }}</code>
                    <p class="mt-3 text-xs text-emerald-700">Paste it into the EuroCross AI plugin settings in WordPress (Connection → API key), or use Connect with EuroCross. Your key works while your account has active access.</p>
                </div>
            @endif

            <div class="table-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-plum-900/6 px-6 py-4">
                    <div>
                        <h2 class="text-sm font-bold text-plum-900">API keys & plugin access</h2>
                        <p class="mt-1 text-xs text-plum-900/40">Use an <strong>eurocross</strong> key in the WordPress plugin. If you connected your site from WordPress, a key is created automatically during your trial.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="buyOpen = true" class="portal-btn !py-2 !bg-plum-900 hover:!bg-plum-800"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> Buy / recharge plan</button>
                        @if ($settings->hasActiveSubscription())
                            <form method="POST" action="{{ route('dashboard.keys.generate') }}">
                                @csrf
                                <button type="submit" class="portal-btn !py-2"><i class="fa-solid fa-key" aria-hidden="true"></i> Generate API key</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="border-b border-plum-900/6 px-6 py-4 space-y-2 text-sm text-plum-900/70">
                    <p>1. Install and activate the EuroCross AI plugin in your WordPress (WooCommerce) admin. 2. In plugin settings, click <strong>Connect with EuroCross</strong> (recommended) or paste an API key from here. 3. Start using translations during your 7-day trial (a free key is provided via connect). 4. After your trial ends, purchase a plan below (account required) to activate access; your existing key will work again after payment is confirmed.</p>
                    @if ($settings->isTrial() && ! $settings->hasActiveSubscription())
                        <p class="text-xs text-plum-900/50">No new key is generated from this page during the free trial. Use the key provided by WordPress Connect.</p>
                    @elseif (! $access['allowed'])
                        <p class="text-xs font-medium text-amber-800">A paid plan is required to activate or generate API keys. Choose a package to continue.</p>
                    @endif
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
                        <p class="px-6 py-8 text-center text-sm text-plum-900/40">
                            @if ($access['allowed'] && $settings->isTrial() && ! $settings->hasActiveSubscription())
                                No keys yet. Connect your WordPress site from the plugin to receive your free trial API key.
                            @elseif ($settings->hasActiveSubscription())
                                No keys yet. Connect your WordPress site or generate one above.
                            @else
                                No active key yet. Purchase a plan to activate API access.
                            @endif
                        </p>
                    @endforelse
                </div>
            </div>

            <div x-show="buyOpen" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" aria-modal="true" role="dialog">
                <div class="absolute inset-0 bg-plum-950/60" @click="buyOpen = false"></div>
                <div class="relative z-10 w-full max-w-4xl rounded-2xl bg-white p-6 shadow-2xl max-h-[90vh] overflow-y-auto" @click.stop>
                    <div class="flex items-start justify-between gap-4 border-b border-plum-900/6 pb-4">
                        <div>
                            <h3 class="text-lg font-bold text-plum-900">Purchase API access</h3>
                            <p class="mt-1 text-sm text-plum-900/60">Choose a plan. You will complete secure payment with Gumroad while signed in to this account, so your subscription and API key are activated for <strong>{{ $user->email }}</strong>.</p>
                        </div>
                        <button type="button" @click="buyOpen = false" class="rounded-lg p-2 text-plum-900/40 hover:bg-plum-900/5" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        @foreach ($plans as $planKey => $plan)
                            <div class="rounded-2xl border {{ $settings->plan === $planKey ? 'border-ec-primary ring-1 ring-ec-primary/20' : 'border-plum-900/8' }} p-5">
                                <p class="text-xs font-semibold uppercase tracking-wide text-plum-900/40">{{ $plan['name'] }}</p>
                                <p class="mt-2 text-3xl font-bold text-plum-900">{{ $plan['price'] }}<span class="text-sm font-medium text-plum-900/40">/month</span></p>
                                <ul class="mt-4 space-y-2 text-sm text-plum-900/70">
                                    <li><i class="fa-solid fa-check text-emerald-600 mr-2" aria-hidden="true"></i>{{ $plan['words'] }}</li>
                                    <li><i class="fa-solid fa-check text-emerald-600 mr-2" aria-hidden="true"></i>{{ $plan['languages'] }}</li>
                                    <li><i class="fa-solid fa-check text-emerald-600 mr-2" aria-hidden="true"></i>API key & plugin access</li>
                                </ul>
                                <a href="{{ route('billing.checkout', $planKey) }}" class="portal-btn mt-5 w-full justify-center">{{ $settings->plan === $planKey ? 'Recharge' : 'Buy' }} {{ $plan['name'] }} <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-5 text-xs text-plum-900/40">Secure checkout is processed by Gumroad. We never store card details. After payment is confirmed, activation usually takes about a minute and your existing key will work again.</p>
                </div>
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
