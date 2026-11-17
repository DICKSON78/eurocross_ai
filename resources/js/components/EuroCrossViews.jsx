import React, { useState } from 'react';
import {
    api,
    EMPTY_VALUE,
    ISSUE_KEY_COMMAND,
    extractText,
    formatCurrency,
    formatDate,
    formatDecimal,
    formatNumber,
    formatPercent,
    humanize,
} from './eurocrossApi.js';

const inputClass =
    'w-full rounded-lg border border-ec-line bg-white px-3 py-2 text-sm text-ec-ink placeholder:text-ec-muted/70 focus:border-ec-light focus:outline-none';
const primaryButtonClass =
    'inline-flex items-center justify-center gap-2 rounded-lg bg-ec-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-ec-dark disabled:cursor-not-allowed disabled:opacity-60';
const secondaryButtonClass =
    'inline-flex items-center justify-center gap-2 rounded-lg border border-ec-line bg-white px-4 py-2 text-sm font-semibold text-ec-primary transition-colors hover:border-ec-light hover:bg-ec-canvas disabled:cursor-not-allowed disabled:opacity-60';

export function SectionHeading({ eyebrow, title, description, actions }) {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="flex flex-col gap-1">
                {eyebrow && (
                    <p className="text-xs font-semibold uppercase tracking-widest text-ec-light">{eyebrow}</p>
                )}
                <h1 className="text-2xl font-semibold tracking-tight text-ec-dark sm:text-3xl">{title}</h1>
                {description && <p className="max-w-2xl text-sm text-ec-muted">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

export function Panel({ title, description, actions, children, className = '', bodyClassName = '' }) {
    return (
        <section className={`overflow-hidden rounded-xl border border-ec-line/70 bg-white shadow-sm ${className}`}>
            {(title || actions) && (
                <div className="flex flex-col gap-2 border-b border-ec-line/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        {title && <h2 className="text-base font-semibold text-ec-dark">{title}</h2>}
                        {description && <p className="mt-0.5 text-xs text-ec-muted">{description}</p>}
                    </div>
                    {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                </div>
            )}
            <div className={`p-5 ${bodyClassName}`}>{children}</div>
        </section>
    );
}

export function ErrorBanner({ message }) {
    if (!message) {
        return null;
    }

    return (
        <div
            role="alert"
            aria-live="assertive"
            className="mb-4 rounded-lg border border-ec-accent/40 bg-ec-accent/5 px-4 py-3 text-sm text-ec-dark"
        >
            {message}
        </div>
    );
}

export function Notice({ children, tone = 'info' }) {
    const toneClass = tone === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-ec-line bg-ec-canvas text-ec-muted';

    return (
        <p role="status" aria-live="polite" className={`rounded-lg border px-3 py-2 text-sm ${toneClass}`}>
            {children}
        </p>
    );
}

export function EmptyState({ title = 'No data yet', description }) {
    return (
        <div className="rounded-xl border border-dashed border-ec-line bg-white/60 px-6 py-12 text-center">
            <p className="text-base font-semibold text-ec-dark">{title}</p>
            {description && <p className="mx-auto mt-1 max-w-md text-sm text-ec-muted">{description}</p>}
        </div>
    );
}

export function LoadingState({ label = 'Loading…' }) {
    return (
        <div className="rounded-xl border border-ec-line/70 bg-white px-6 py-12 text-center text-sm text-ec-muted" role="status" aria-live="polite">
            {label}
        </div>
    );
}

export function ResourceBoundary({ resource, emptyDescription, skeleton, children }) {
    if (!resource || (resource.loading && !resource.data)) {
        return skeleton ?? <LoadingState />;
    }

    if (resource.error && !resource.data) {
        return <ErrorBanner message={resource.error} />;
    }

    if (!resource.data) {
        return <EmptyState description={emptyDescription} />;
    }

    return (
        <>
            {resource.error && <ErrorBanner message={resource.error} />}
            {children(resource.data)}
        </>
    );
}

function Field({ label, htmlFor, hint, children }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={htmlFor} className="text-xs font-semibold uppercase tracking-wide text-ec-muted">
                {label}
            </label>
            {children}
            {hint && <p className="text-xs text-ec-muted">{hint}</p>}
        </div>
    );
}

function KeyValue({ label, value }) {
    return (
        <div className="rounded-lg border border-ec-line/60 bg-ec-canvas/60 px-3 py-2.5">
            <dt className="text-[11px] font-semibold uppercase tracking-wide text-ec-muted">{label}</dt>
            <dd className="mt-1 break-words text-sm font-medium text-ec-ink">{value ?? EMPTY_VALUE}</dd>
        </div>
    );
}

function StatCard({ label, value, hint, emphasize = false }) {
    return (
        <div className="rounded-xl border border-ec-line/70 bg-white p-5 shadow-sm">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-ec-muted">{label}</p>
            <p className={`mt-2 text-2xl font-semibold ${emphasize ? 'text-ec-primary' : 'text-ec-ink'}`}>
                {value ?? EMPTY_VALUE}
            </p>
            {hint && <p className="mt-1 text-xs text-ec-muted">{hint}</p>}
        </div>
    );
}

function ResponseDetails({ value }) {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'string') {
        return <p className="text-sm text-ec-ink">{value}</p>;
    }

    if (Array.isArray(value)) {
        return (
            <ul className="flex flex-col gap-1 text-sm text-ec-ink">
                {value.map((item, index) => (
                    <li key={index}>{typeof item === 'object' ? JSON.stringify(item) : String(item)}</li>
                ))}
            </ul>
        );
    }

    if (typeof value === 'object') {
        return (
            <dl className="grid gap-2 sm:grid-cols-2">
                {Object.entries(value).map(([key, item]) => (
                    <div key={key} className="rounded-lg bg-ec-canvas px-3 py-2">
                        <dt className="text-[11px] font-semibold uppercase tracking-wide text-ec-muted">
                            {humanize(key)}
                        </dt>
                        <dd className="mt-0.5 break-words text-sm text-ec-ink">
                            {item === null || item === undefined
                                ? EMPTY_VALUE
                                : typeof item === 'object'
                                  ? JSON.stringify(item)
                                  : String(item)}
                        </dd>
                    </div>
                ))}
            </dl>
        );
    }

    return <p className="text-sm text-ec-ink">{String(value)}</p>;
}

async function copyCommand(onResult) {
    try {
        if (!navigator.clipboard?.writeText) {
            throw new Error('Clipboard access is unavailable.');
        }
        await navigator.clipboard.writeText(ISSUE_KEY_COMMAND);
        onResult('Copied to clipboard.');
    } catch {
        onResult('Copy failed — select the command and copy it manually.');
    }
}

export function SetupScreen({ healthResource, error, onSaveKey }) {
    const [keyInput, setKeyInput] = useState('');
    const [message, setMessage] = useState('');
    const [copyMessage, setCopyMessage] = useState('');
    const health = healthResource?.data ?? {};

    const handleSubmit = (event) => {
        event.preventDefault();
        try {
            onSaveKey(keyInput);
            setKeyInput('');
            setMessage('API key saved to this browser. Loading your dashboard…');
        } catch (saveError) {
            setMessage(saveError.message);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-ec-canvas px-4 py-10">
            <div className="w-full max-w-xl overflow-hidden rounded-2xl border border-ec-line/70 bg-white shadow-lg">
                <div className="bg-ec-dark px-6 py-7 text-white sm:px-8">
                    <p className="text-xs font-semibold uppercase tracking-widest text-white/70">EuroCross AI</p>
                    <h1 className="mt-2 text-2xl font-semibold">Connect your account</h1>
                    <p className="mt-2 text-sm text-white/80">
                        This dashboard reads live data from the EuroCross API. Add an API key to continue.
                    </p>
                </div>

                <div className="flex flex-col gap-5 px-6 py-7 sm:px-8">
                    <ErrorBanner message={error} />

                    <div className="rounded-lg border border-ec-line bg-ec-canvas/60 p-4">
                        <p className="text-sm font-semibold text-ec-dark">1. Issue an API key</p>
                        <p className="mt-1 text-xs text-ec-muted">
                            Run this command in the Laravel project directory:
                        </p>
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                            <code className="min-w-0 flex-1 overflow-x-auto rounded-md bg-white px-3 py-2 font-mono text-xs text-ec-primary ring-1 ring-ec-line/70">
                                {ISSUE_KEY_COMMAND}
                            </code>
                            <button type="button" className={secondaryButtonClass} onClick={() => copyCommand(setCopyMessage)}>
                                Copy
                            </button>
                        </div>
                        {copyMessage && (
                            <p role="status" aria-live="polite" className="mt-2 text-xs text-ec-muted">
                                {copyMessage}
                            </p>
                        )}
                    </div>

                    <div>
                        <p className="text-sm font-semibold text-ec-dark">2. Save the key</p>
                        <p className="mt-1 text-xs text-ec-muted">
                            For local development you may instead set <code className="font-mono">VITE_EUROCROSS_API_KEY</code> in your
                            environment. The key stays in this browser and is sent only to this site.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="flex flex-col gap-3">
                        <Field label="API key" htmlFor="setup-api-key">
                            <input
                                id="setup-api-key"
                                type="password"
                                autoComplete="off"
                                className={inputClass}
                                placeholder="Paste your API key"
                                value={keyInput}
                                onChange={(event) => setKeyInput(event.target.value)}
                            />
                        </Field>
                        <button type="submit" className={primaryButtonClass}>
                            Save key and continue
                        </button>
                        {message && (
                            <p role="status" aria-live="polite" className="text-sm text-ec-muted">
                                {message}
                            </p>
                        )}
                    </form>

                    <div className="flex flex-wrap items-center gap-2 border-t border-ec-line/60 pt-4 text-xs text-ec-muted">
                        <span>API status:</span>
                        <span className="font-semibold text-ec-primary">{health.status ?? 'checking…'}</span>
                        {health.version && <span>v{String(health.version)}</span>}
                        {health.demo_mode === true && (
                            <span className="rounded-full bg-ec-accent/10 px-2 py-0.5 font-semibold text-ec-accent">Demo mode</span>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function OverviewSkeleton() {
    return (
        <div className="flex flex-col gap-6" aria-busy="true">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {['Monthly recurring revenue', 'Active accounts', 'Translations this month', 'Tokens used', 'Monthly profit', 'Words remaining'].map(
                    (label) => (
                        <StatCard key={label} label={label} value={EMPTY_VALUE} />
                    ),
                )}
            </div>
            <LoadingState label="Loading account summary…" />
        </div>
    );
}

export function OverviewView({ summaryResource }) {
    return (
        <>
            <SectionHeading
                eyebrow="Overview"
                title="Your EuroCross command center"
                description="A live view of subscription performance and account usage."
                actions={
                    <span className="rounded-full bg-white px-3 py-1.5 text-xs font-medium text-ec-muted ring-1 ring-ec-line/70">
                        Live data
                    </span>
                }
            />
            <ResourceBoundary
                resource={summaryResource}
                emptyDescription="The account summary endpoint returned no data yet."
                skeleton={<OverviewSkeleton />}
            >
                {(data) => {
                    const account = data.account ?? {};
                    const usage = data.usage ?? {};
                    const providers = data.providers ?? {};
                    const rawPercent = Number(usage.usage_percentage);
                    const hasPercent = Number.isFinite(rawPercent);
                    const usagePercent = hasPercent ? Math.min(Math.max(rawPercent, 0), 100) : 0;

                    return (
                        <div className="flex flex-col gap-6">
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <StatCard
                                    label="Monthly recurring revenue"
                                    value={formatCurrency(account.monthly_recurring_revenue)}
                                    emphasize
                                />
                                <StatCard label="Active accounts" value={formatNumber(account.active_accounts)} />
                                <StatCard label="Translations this month" value={formatNumber(usage.strings_this_month)} />
                                <StatCard label="Tokens used" value={formatNumber(usage.tokens_this_month)} />
                                <StatCard label="Monthly profit" value={formatCurrency(account.monthly_profit)} />
                                <StatCard
                                    label="Words remaining"
                                    value={formatNumber(usage.remaining_words)}
                                    hint={
                                        hasPercent
                                            ? `${formatDecimal(rawPercent)}% of the plan allowance used`
                                            : EMPTY_VALUE
                                    }
                                />
                            </div>

                            <Panel title="Word allowance" description="Consumption for the current billing period.">
                                <div className="flex flex-col gap-2">
                                    <div className="h-2.5 w-full overflow-hidden rounded-full bg-ec-canvas">
                                        <div
                                            className="h-full rounded-full bg-ec-primary transition-[width] duration-500"
                                            style={{ width: `${usagePercent}%` }}
                                        />
                                    </div>
                                    <div className="flex flex-wrap justify-between gap-2 text-xs text-ec-muted">
                                        <span>{formatNumber(usage.words_this_month)} words used</span>
                                        <span>{formatNumber(usage.remaining_words)} words remaining</span>
                                    </div>
                                </div>
                            </Panel>

                            <Panel title="Providers" description="Active translation and AI routing.">
                                <dl className="grid gap-3 sm:grid-cols-3">
                                    <KeyValue label="AI provider" value={providers.ai ?? EMPTY_VALUE} />
                                    <KeyValue label="Primary" value={providers.primary ?? EMPTY_VALUE} />
                                    <KeyValue label="Fallback" value={providers.fallback ?? EMPTY_VALUE} />
                                </dl>
                            </Panel>

                            <Panel title="Account status" description="Current plan and standing.">
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    <KeyValue label="Plan" value={humanize(account.plan)} />
                                    <KeyValue label="Status" value={humanize(account.status)} />
                                </dl>
                            </Panel>
                        </div>
                    );
                }}
            </ResourceBoundary>
        </>
    );
}

function UsageBarChart({ series }) {
    const points = series.map((point, index) => ({
        key: `${point?.date ?? 'day'}-${index}`,
        label: point?.date ? formatDate(point.date) : `Day ${index + 1}`,
        value: Math.max(Number(point?.words) || 0, 0),
        raw: point ?? {},
    }));
    const maxValue = Math.max(...points.map((point) => point.value), 0);

    return (
        <div>
            <div
                className="flex h-52 items-end gap-1 overflow-x-auto pb-2"
                role="img"
                aria-label="Words translated per day"
            >
                {points.map((point) => {
                    const height = maxValue > 0 ? Math.max((point.value / maxValue) * 100, point.value > 0 ? 3 : 0) : 0;

                    return (
                        <div
                            key={point.key}
                            className="group flex h-full min-w-2 flex-1 flex-col justify-end"
                            title={`${point.label}: ${formatNumber(point.value)} words`}
                        >
                            <div
                                className="chart-bar w-full rounded-t-md bg-ec-light"
                                style={{ height: `${height}%` }}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="flex justify-between text-[11px] text-ec-muted">
                <span>{points[0].label}</span>
                <span>{points[points.length - 1].label}</span>
            </div>
        </div>
    );
}

export function UsageView({ analyticsResource }) {
    return (
        <>
            <SectionHeading
                eyebrow="Usage"
                title="Usage analytics"
                description="Daily translation activity reported by the API."
            />
            <ResourceBoundary
                resource={analyticsResource}
                emptyDescription="No analytics have been recorded yet."
                skeleton={<LoadingState label="Loading analytics…" />}
            >
                {(data) => {
                    const series = Array.isArray(data.series) ? data.series : [];
                    const totals = data.totals ?? {};
                    const limits = data.plan_limits ?? {};

                    if (series.length === 0) {
                        return <EmptyState description="No analytics have been recorded yet." />;
                    }

                    return (
                        <div className="flex flex-col gap-6">
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <StatCard label="Total tokens" value={formatNumber(totals.tokens)} />
                                <StatCard label="Total strings" value={formatNumber(totals.strings)} />
                                <StatCard label="Total words" value={formatNumber(totals.words)} />
                                <StatCard label="Total cost" value={formatCurrency(totals.cost)} />
                            </div>

                            <Panel title="Daily words" description="Bar heights reflect the returned series values.">
                                <UsageBarChart series={series} />
                            </Panel>

                            <Panel title="Plan limits" description="Limits applied to the current plan.">
                                <dl className="grid gap-3 sm:grid-cols-3">
                                    <KeyValue label="Word limit" value={formatNumber(limits.words)} />
                                    <KeyValue label="Languages" value={formatNumber(limits.languages)} />
                                    <KeyValue label="Rate limit" value={formatNumber(limits.rate_limit)} />
                                </dl>
                            </Panel>

                            <Panel title="Daily breakdown" description="Most recent days first." bodyClassName="p-0">
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-[560px] text-left text-sm">
                                        <thead className="bg-ec-canvas text-[11px] uppercase tracking-wide text-ec-muted">
                                            <tr>
                                                <th scope="col" className="px-5 py-3 font-semibold">Date</th>
                                                <th scope="col" className="px-5 py-3 text-right font-semibold">Tokens</th>
                                                <th scope="col" className="px-5 py-3 text-right font-semibold">Strings</th>
                                                <th scope="col" className="px-5 py-3 text-right font-semibold">Words</th>
                                                <th scope="col" className="px-5 py-3 text-right font-semibold">Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-ec-line/60">
                                            {[...series].reverse().map((point, index) => (
                                                <tr key={`${point?.date ?? 'row'}-${index}`}>
                                                    <td className="px-5 py-2.5 text-ec-ink">
                                                        {point?.date ? formatDate(point.date) : EMPTY_VALUE}
                                                    </td>
                                                    <td className="px-5 py-2.5 text-right text-ec-ink">{formatNumber(point?.tokens)}</td>
                                                    <td className="px-5 py-2.5 text-right text-ec-ink">{formatNumber(point?.strings)}</td>
                                                    <td className="px-5 py-2.5 text-right text-ec-ink">{formatNumber(point?.words)}</td>
                                                    <td className="px-5 py-2.5 text-right text-ec-ink">{formatCurrency(point?.cost)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </Panel>
                        </div>
                    );
                }}
            </ResourceBoundary>
        </>
    );
}

const CHECKOUT_PLANS = [
    { id: 'starter', label: 'Starter' },
    { id: 'growth', label: 'Growth' },
    { id: 'scale', label: 'Scale' },
];

export function BillingView({ settingsResource, onRefresh }) {
    const [email, setEmail] = useState('');
    const [busyPlan, setBusyPlan] = useState(null);
    const [error, setError] = useState('');

    const handleCheckout = async (plan) => {
        if (!email.trim()) {
            setError('Enter your billing email before starting checkout.');
            return;
        }

        setBusyPlan(plan);
        setError('');

        try {
            const payload = await api('/checkout', {
                method: 'POST',
                body: { plan, email: email.trim() },
            });

            if (!payload.checkout_url) {
                throw new Error('The checkout response did not include a redirect URL.');
            }

            onRefresh?.();
            window.location.assign(payload.checkout_url);
        } catch (checkoutError) {
            setError(checkoutError.message);
        } finally {
            setBusyPlan(null);
        }
    };

    return (
        <>
            <SectionHeading
                eyebrow="Billing"
                title="Plan and subscription"
                description="Manage checkout, review your trial, and follow subscription status."
            />
            <ErrorBanner message={error} />

            <ResourceBoundary
                resource={settingsResource}
                emptyDescription="No billing information is available yet."
                skeleton={<LoadingState label="Loading billing details…" />}
            >
                {(data) => {
                    const settings = data.settings ?? {};
                    const trial = settings.trial ?? {};
                    const subscription = settings.subscription ?? {};
                    const access = settings.access ?? {};
                    const features = Array.isArray(settings.plan_features) ? settings.plan_features : [];

                    return (
                        <div className="flex flex-col gap-6">
                            <div className="grid gap-6 lg:grid-cols-2">
                                <Panel title="Current plan" description="Your active EuroCross plan.">
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <KeyValue label="Plan" value={humanize(settings.plan_name ?? settings.plan)} />
                                        <KeyValue label="Price" value={formatCurrency(settings.plan_price)} />
                                        <KeyValue label="Access" value={access.allowed ? 'Allowed' : 'Restricted'} />
                                        <KeyValue label="Reason" value={access.reason ?? EMPTY_VALUE} />
                                    </dl>
                                    {features.length > 0 && (
                                        <div className="mt-5">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-ec-muted">Included features</p>
                                            <ul className="mt-2 flex flex-wrap gap-2">
                                                {features.map((feature, index) => (
                                                    <li
                                                        key={index}
                                                        className="rounded-full bg-ec-canvas px-3 py-1 text-xs text-ec-primary ring-1 ring-ec-line/60"
                                                    >
                                                        {typeof feature === 'object' ? JSON.stringify(feature) : String(feature)}
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
                                </Panel>

                                <Panel title="Trial and subscription" description="Dates and status returned by the API.">
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <KeyValue label="Trial active" value={trial.active ? 'Yes' : 'No'} />
                                        <KeyValue label="Days remaining" value={formatNumber(trial.days_remaining)} />
                                        <KeyValue label="Trial expires" value={formatDate(trial.expires_at)} />
                                        <KeyValue label="Grace period ends" value={formatDate(trial.grace_ends_at)} />
                                        <KeyValue label="Subscription status" value={humanize(subscription.status)} />
                                        <KeyValue label="Provider" value={humanize(subscription.provider)} />
                                        <KeyValue label="Subscription ID" value={subscription.id ?? EMPTY_VALUE} />
                                        <KeyValue
                                            label="Current period ends"
                                            value={formatDate(subscription.current_period_end)}
                                        />
                                    </dl>
                                </Panel>
                            </div>

                            <Panel
                                title="Choose a plan"
                                description="Checkout is handled by the configured payment provider."
                            >
                                <div className="flex flex-col gap-4">
                                    <Field label="Billing email" htmlFor="billing-email">
                                        <input
                                            id="billing-email"
                                            type="email"
                                            className={inputClass}
                                            placeholder="you@example.com"
                                            value={email}
                                            onChange={(event) => setEmail(event.target.value)}
                                        />
                                    </Field>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        {CHECKOUT_PLANS.map((plan) => (
                                            <button
                                                key={plan.id}
                                                type="button"
                                                className={secondaryButtonClass}
                                                onClick={() => handleCheckout(plan.id)}
                                                disabled={busyPlan !== null}
                                                aria-busy={busyPlan === plan.id}
                                            >
                                                {busyPlan === plan.id ? 'Opening…' : plan.label}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </Panel>
                        </div>
                    );
                }}
            </ResourceBoundary>
        </>
    );
}

export function VatView({ vatRatesResource }) {
    const [countryCode, setCountryCode] = useState('');
    const [vatNumber, setVatNumber] = useState('');
    const [result, setResult] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const handleCheck = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        setResult(null);

        try {
            const payload = await api('/vat/check', {
                method: 'POST',
                body: { country_code: countryCode.trim().toUpperCase(), vat_number: vatNumber.trim() },
            });
            setResult(payload.data ?? payload);
        } catch (checkError) {
            setError(checkError.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <SectionHeading
                eyebrow="VAT & Compliance"
                title="VAT rates and validation"
                description="Reference rates and live VAT number verification."
            />
            <ErrorBanner message={error} />

            <ResourceBoundary
                resource={vatRatesResource}
                emptyDescription="No VAT rates have been configured yet."
                skeleton={<LoadingState label="Loading VAT rates…" />}
            >
                {(data) => {
                    const rates = Array.isArray(data) ? data : [];

                    if (rates.length === 0) {
                        return <EmptyState description="No VAT rates have been configured yet." />;
                    }

                    return (
                        <Panel title="VAT rates" description="Rates currently configured for each country.">
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[420px] text-left text-sm">
                                    <thead className="bg-ec-canvas text-[11px] uppercase tracking-wide text-ec-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-3 font-semibold">Code</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Country</th>
                                            <th scope="col" className="px-4 py-3 text-right font-semibold">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-ec-line/60">
                                        {rates.map((rate, index) => (
                                            <tr key={`${rate?.country_code ?? 'country'}-${index}`}>
                                                <td className="px-4 py-2.5 font-medium text-ec-primary">
                                                    {rate?.country_code ?? EMPTY_VALUE}
                                                </td>
                                                <td className="px-4 py-2.5 text-ec-ink">{rate?.country_name ?? EMPTY_VALUE}</td>
                                                <td className="px-4 py-2.5 text-right text-ec-ink">{formatPercent(rate?.rate)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Panel>
                    );
                }}
            </ResourceBoundary>

            <div className="mt-6">
                <Panel title="Check a VAT number" description="Validation is performed by the EuroCross API.">
                    <form onSubmit={handleCheck} className="flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Country" htmlFor="vat-country">
                                <input
                                    id="vat-country"
                                    className={inputClass}
                                    placeholder="e.g. DE"
                                    value={countryCode}
                                    onChange={(event) => setCountryCode(event.target.value)}
                                    required
                                />
                            </Field>
                            <Field label="VAT number" htmlFor="vat-number">
                                <input
                                    id="vat-number"
                                    className={inputClass}
                                    placeholder="VAT number"
                                    value={vatNumber}
                                    onChange={(event) => setVatNumber(event.target.value)}
                                    required
                                />
                            </Field>
                        </div>
                        <div>
                            <button type="submit" className={primaryButtonClass} disabled={busy} aria-busy={busy}>
                                {busy ? 'Checking…' : 'Check VAT number'}
                            </button>
                        </div>
                        {result !== null && (
                            <div className="rounded-lg border border-ec-line bg-ec-canvas/60 p-4">
                                <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-ec-muted">API response</p>
                                <ResponseDetails value={result} />
                            </div>
                        )}
                    </form>
                </Panel>
            </div>
        </>
    );
}

export function ApiKeysView({ apiKeysResource, onRefresh }) {
    const [name, setName] = useState('');
    const [provider, setProvider] = useState('');
    const [secret, setSecret] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    const handleSubmit = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        setNotice('');

        try {
            await api('/api-keys', {
                method: 'POST',
                body: { name: name.trim(), provider: provider.trim(), key: secret.trim() },
            });
            setName('');
            setProvider('');
            setSecret('');
            setNotice('API key added.');
            onRefresh?.();
        } catch (saveError) {
            setError(saveError.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <SectionHeading
                eyebrow="API Keys"
                title="Provider credentials"
                description="Keys are stored server-side; secret values are never displayed here."
            />
            <ErrorBanner message={error} />
            {notice && <Notice tone="success">{notice}</Notice>}

            <Panel title="Configured keys" description="Status and usage metadata for each provider key.">
                <ResourceBoundary
                    resource={apiKeysResource}
                    emptyDescription="No provider keys have been added yet."
                    skeleton={<LoadingState label="Loading API keys…" />}
                >
                    {(data) => {
                        const keys = Array.isArray(data) ? data : [];

                        if (keys.length === 0) {
                            return <EmptyState description="No provider keys have been added yet." />;
                        }

                        return (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-left text-sm">
                                    <thead className="bg-ec-canvas text-[11px] uppercase tracking-wide text-ec-muted">
                                        <tr>
                                            <th scope="col" className="px-4 py-3 font-semibold">Name</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Provider</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Key</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Active</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Last used</th>
                                            <th scope="col" className="px-4 py-3 font-semibold">Created</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-ec-line/60">
                                        {keys.map((key, index) => (
                                            <tr key={key?.id ?? index}>
                                                <td className="px-4 py-2.5 text-ec-ink">{key?.name ?? EMPTY_VALUE}</td>
                                                <td className="px-4 py-2.5 text-ec-ink">{humanize(key?.provider)}</td>
                                                <td className="px-4 py-2.5">
                                                    <span className="font-mono tracking-widest text-ec-muted" aria-label="Secret value hidden">
                                                        ••••••••••••
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                            key?.active
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : 'bg-ec-canvas text-ec-muted'
                                                        }`}
                                                    >
                                                        {key?.active ? 'Active' : 'Inactive'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-ec-muted">{formatDate(key?.last_used_at, true)}</td>
                                                <td className="px-4 py-2.5 text-ec-muted">{formatDate(key?.created_at, true)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        );
                    }}
                </ResourceBoundary>
            </Panel>

            <div className="mt-6">
                <Panel title="Add a key" description="Submit a new provider credential. It will not be shown after saving.">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Name" htmlFor="key-name">
                                <input
                                    id="key-name"
                                    className={inputClass}
                                    value={name}
                                    onChange={(event) => setName(event.target.value)}
                                    required
                                />
                            </Field>
                            <Field label="Provider" htmlFor="key-provider">
                                <input
                                    id="key-provider"
                                    className={inputClass}
                                    placeholder="e.g. openai"
                                    value={provider}
                                    onChange={(event) => setProvider(event.target.value)}
                                    required
                                />
                            </Field>
                        </div>
                        <Field label="Secret key" htmlFor="key-secret">
                            <input
                                id="key-secret"
                                type="password"
                                autoComplete="off"
                                className={inputClass}
                                placeholder="Paste your provider key"
                                value={secret}
                                onChange={(event) => setSecret(event.target.value)}
                                required
                            />
                        </Field>
                        <div>
                            <button type="submit" className={primaryButtonClass} disabled={busy} aria-busy={busy}>
                                {busy ? 'Saving…' : 'Add API key'}
                            </button>
                        </div>
                    </form>
                </Panel>
            </div>
        </>
    );
}

export function ChatbotView({ chatbotResource }) {
    const [message, setMessage] = useState('');
    const [reply, setReply] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const handleSend = async (event) => {
        event.preventDefault();
        setBusy(true);
        setError('');
        setReply('');

        try {
            const payload = await api('/chatbot/message', {
                method: 'POST',
                body: { message: message.trim() },
            });
            setReply(extractText(payload) || 'The chatbot returned an empty response.');
            setMessage('');
        } catch (chatError) {
            setError(chatError.message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <SectionHeading
                eyebrow="Chatbot"
                title="Chatbot assistant"
                description="Check configuration and send a message through the live chatbot endpoint."
            />
            <ErrorBanner message={error} />

            <Panel title="Status" description="Current chatbot configuration and remaining allowance.">
                <ResourceBoundary
                    resource={chatbotResource}
                    emptyDescription="Chatbot status is unavailable."
                    skeleton={<LoadingState label="Loading chatbot status…" />}
                >
                    {(data) => (
                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <KeyValue label="Configured" value={data.is_configured ? 'Yes' : 'No'} />
                            <KeyValue label="Plan" value={humanize(data.plan)} />
                            <KeyValue label="Rate limit" value={formatNumber(data.rate_limit)} />
                            <KeyValue label="Remaining" value={formatNumber(data.remaining)} />
                        </dl>
                    )}
                </ResourceBoundary>
            </Panel>

            <div className="mt-6">
                <Panel title="Send a message" description="Replies come directly from the API.">
                    <form onSubmit={handleSend} className="flex flex-col gap-4">
                        <Field label="Message" htmlFor="chat-message">
                            <textarea
                                id="chat-message"
                                rows={4}
                                className={inputClass}
                                placeholder="Ask the chatbot something"
                                value={message}
                                onChange={(event) => setMessage(event.target.value)}
                                required
                            />
                        </Field>
                        <div>
                            <button type="submit" className={primaryButtonClass} disabled={busy} aria-busy={busy}>
                                {busy ? 'Sending…' : 'Send message'}
                            </button>
                        </div>
                        {reply && (
                            <div className="rounded-lg border border-ec-line bg-ec-canvas/60 p-4" aria-live="polite">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ec-muted">Reply</p>
                                <p className="whitespace-pre-wrap text-sm text-ec-ink">{reply}</p>
                            </div>
                        )}
                    </form>
                </Panel>
            </div>
        </>
    );
}

export function SettingsView({ settingsResource, onSaveKey, onRefresh }) {
    const [keyInput, setKeyInput] = useState('');
    const [keyMessage, setKeyMessage] = useState('');
    const [copyMessage, setCopyMessage] = useState('');
    const [trialBusy, setTrialBusy] = useState(false);
    const [trialError, setTrialError] = useState('');
    const [trialMessage, setTrialMessage] = useState('');

    const handleSaveKey = (event) => {
        event.preventDefault();
        try {
            onSaveKey(keyInput);
            setKeyInput('');
            setKeyMessage('API key saved to this browser.');
        } catch (saveError) {
            setKeyMessage(saveError.message);
        }
    };

    const handleStartTrial = async () => {
        setTrialBusy(true);
        setTrialError('');
        setTrialMessage('');

        try {
            const payload = await api('/trial/start', { method: 'POST', body: {} });
            setTrialMessage(extractText(payload) || 'Trial started successfully.');
            onRefresh?.();
        } catch (startError) {
            setTrialError(startError.message);
        } finally {
            setTrialBusy(false);
        }
    };

    return (
        <>
            <SectionHeading
                eyebrow="Settings"
                title="Account settings"
                description="Locale, plan details, trial activation, and API access."
            />

            <div className="grid gap-6 xl:grid-cols-2">
                <Panel title="Account" description="Values reported by the settings endpoint.">
                    <ResourceBoundary
                        resource={settingsResource}
                        emptyDescription="No settings are available yet."
                        skeleton={<LoadingState label="Loading settings…" />}
                    >
                        {(data) => {
                            const settings = data.settings ?? {};
                            const access = settings.access ?? {};

                            return (
                                <dl className="grid gap-3 sm:grid-cols-2">
                                    <KeyValue label="Locale" value={settings.locale ?? EMPTY_VALUE} />
                                    <KeyValue label="Plan" value={humanize(settings.plan)} />
                                    <KeyValue label="Plan name" value={settings.plan_name ?? EMPTY_VALUE} />
                                    <KeyValue label="Access allowed" value={access.allowed ? 'Yes' : 'No'} />
                                    <KeyValue label="Access reason" value={access.reason ?? EMPTY_VALUE} />
                                </dl>
                            );
                        }}
                    </ResourceBoundary>

                    <div className="mt-5 border-t border-ec-line/60 pt-5">
                        <p className="text-sm font-semibold text-ec-dark">Start a trial</p>
                        <p className="mt-1 text-xs text-ec-muted">Activate the trial for the current account.</p>
                        <div className="mt-3">
                            <button type="button" className={primaryButtonClass} onClick={handleStartTrial} disabled={trialBusy} aria-busy={trialBusy}>
                                {trialBusy ? 'Starting…' : 'Start trial'}
                            </button>
                        </div>
                        <ErrorBanner message={trialError} />
                        {trialMessage && (
                            <div className="mt-3">
                                <Notice tone="success">{trialMessage}</Notice>
                            </div>
                        )}
                    </div>
                </Panel>

                <Panel title="API access" description="Store the key used for protected requests on this device.">
                    <div className="flex flex-col gap-4">
                        <div className="rounded-lg border border-ec-line bg-ec-canvas/60 p-4">
                            <p className="text-sm font-semibold text-ec-dark">Issue a key</p>
                            <p className="mt-1 text-xs text-ec-muted">Run this in the Laravel project directory:</p>
                            <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                                <code className="min-w-0 flex-1 overflow-x-auto rounded-md bg-white px-3 py-2 font-mono text-xs text-ec-primary ring-1 ring-ec-line/70">
                                    {ISSUE_KEY_COMMAND}
                                </code>
                                <button type="button" className={secondaryButtonClass} onClick={() => copyCommand(setCopyMessage)}>
                                    Copy
                                </button>
                            </div>
                            {copyMessage && (
                                <p role="status" aria-live="polite" className="mt-2 text-xs text-ec-muted">
                                    {copyMessage}
                                </p>
                            )}
                        </div>

                        <form onSubmit={handleSaveKey} className="flex flex-col gap-3">
                            <Field label="API key" htmlFor="settings-api-key" hint="The stored key is never shown again.">
                                <input
                                    id="settings-api-key"
                                    type="password"
                                    autoComplete="off"
                                    className={inputClass}
                                    placeholder="Paste a new API key"
                                    value={keyInput}
                                    onChange={(event) => setKeyInput(event.target.value)}
                                />
                            </Field>
                            <div>
                                <button type="submit" className={secondaryButtonClass}>
                                    Save API key
                                </button>
                            </div>
                            {keyMessage && (
                                <p role="status" aria-live="polite" className="text-sm text-ec-muted">
                                    {keyMessage}
                                </p>
                            )}
                        </form>
                    </div>
                </Panel>
            </div>
        </>
    );
}
