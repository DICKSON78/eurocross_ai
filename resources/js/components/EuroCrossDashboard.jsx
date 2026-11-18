import React, { useCallback, useEffect, useState } from 'react';
import { api, currentApiKey, humanize, writeApiKey } from './eurocrossApi.js';
import {
    ApiKeysView,
    BillingView,
    ChatbotView,
    OverviewView,
    SettingsView,
    SetupScreen,
    UsageView,
    VatView,
} from './EuroCrossViews.jsx';

const NAV_ITEMS = [
    { id: 'overview', label: 'Overview', Icon: OverviewIcon },
    { id: 'usage', label: 'Usage', Icon: UsageIcon },
    { id: 'billing', label: 'Billing', Icon: BillingIcon },
    { id: 'vat', label: 'VAT & Compliance', Icon: VatIcon },
    { id: 'keys', label: 'API Keys', Icon: KeyIcon },
    { id: 'chatbot', label: 'Chatbot', Icon: ChatbotIcon },
    { id: 'settings', label: 'Settings', Icon: SettingsIcon },
];

const PROTECTED_ENDPOINTS = [
    ['settings', '/settings'],
    ['summary', '/account/summary'],
    ['analytics', '/account/analytics?days=30'],
    ['apiKeys', '/api-keys'],
    ['vatRates', '/vat/rates'],
    ['chatbot', '/chatbot/status'],
];

const idleResource = () => ({ data: null, loading: false, error: null });

const initialResources = () => ({
    health: { data: null, loading: true, error: null },
    settings: idleResource(),
    summary: idleResource(),
    analytics: idleResource(),
    apiKeys: idleResource(),
    vatRates: idleResource(),
    chatbot: idleResource(),
});

function Icon({ children, className = 'size-5' }) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

function OverviewIcon() {
    return (
        <Icon>
            <rect x="3" y="3" width="7" height="7" rx="1" />
            <rect x="14" y="3" width="7" height="7" rx="1" />
            <rect x="3" y="14" width="7" height="7" rx="1" />
            <rect x="14" y="14" width="7" height="7" rx="1" />
        </Icon>
    );
}

function UsageIcon() {
    return (
        <Icon>
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
        </Icon>
    );
}

function BillingIcon() {
    return (
        <Icon>
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <path d="M2 10h20M6 15h4" />
        </Icon>
    );
}

function VatIcon() {
    return (
        <Icon>
            <path d="M12 3l8 3v6c0 5-3.4 8.3-8 9-4.6-.7-8-4-8-9V6l8-3z" />
            <path d="M9 12l2 2 4-4" />
        </Icon>
    );
}

function KeyIcon() {
    return (
        <Icon>
            <circle cx="8" cy="15" r="4" />
            <path d="M10.8 12.2L20 3m-4 4l3 3m-5 1l3 3" />
        </Icon>
    );
}

function ChatbotIcon() {
    return (
        <Icon>
            <path d="M21 12a8 8 0 0 1-8 8H7l-4 3 1.2-4.4A8 8 0 1 1 21 12z" />
            <path d="M8 12h.01M12 12h.01M16 12h.01" />
        </Icon>
    );
}

function SettingsIcon() {
    return (
        <Icon>
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21h-4v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3v-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V3h4v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1H21v4h-.1a1.7 1.7 0 0 0-1.5 1z" />
        </Icon>
    );
}

function MenuIcon() {
    return (
        <Icon>
            <path d="M4 6h16M4 12h16M4 18h16" />
        </Icon>
    );
}

function CloseIcon() {
    return (
        <Icon>
            <path d="M6 6l12 12M18 6L6 18" />
        </Icon>
    );
}

function RefreshIcon({ spinning = false }) {
    return (
        <Icon className={`size-4 ${spinning ? 'animate-spin' : ''}`}>
            <path d="M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7" />
        </Icon>
    );
}

export default function EuroCrossDashboard() {
    const [apiKey, setApiKey] = useState(() => {
        const params = new URLSearchParams(window.location.search);
        const keyFromUrl = params.get('key');

        if (keyFromUrl) {
            try {
                return writeApiKey(keyFromUrl);
            } catch {
                // fall through to stored key
            }
        }

        return currentApiKey();
    });
    const [activeView, setActiveView] = useState('overview');
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [authError, setAuthError] = useState('');
    const [resources, setResources] = useState(initialResources);

    const refreshAll = useCallback(async () => {
        const patch = (name, change) => {
            setResources((previous) => ({
                ...previous,
                [name]: { ...previous[name], ...change },
            }));
        };

        patch('health', { loading: true, error: null });
        try {
            const payload = await api('/health', { auth: false });
            patch('health', { data: payload.data ?? payload, loading: false, error: null });
        } catch (error) {
            patch('health', { loading: false, error: error.message });
        }

        if (!apiKey) {
            setAuthError('');
            setResources((previous) => {
                const next = { ...previous };
                Object.keys(next).forEach((name) => {
                    if (name !== 'health') {
                        next[name] = idleResource();
                    }
                });
                return next;
            });
            return;
        }

        setAuthError('');

        await Promise.all(
            PROTECTED_ENDPOINTS.map(async ([name, path]) => {
                patch(name, { loading: true, error: null });

                try {
                    const payload = await api(path);
                    patch(name, { data: payload.data ?? payload, loading: false, error: null });
                } catch (error) {
                    patch(name, { loading: false, error: error.message });
                    if (error.status === 401) {
                        setAuthError('The API key was rejected. Enter a valid key to continue.');
                    }
                }
            }),
        );
    }, [apiKey]);

    useEffect(() => {
        refreshAll();
    }, [refreshAll]);

    const handleSaveKey = useCallback((value) => {
        const savedKey = writeApiKey(value);
        setAuthError('');
        setApiKey(savedKey);
    }, []);

    const handleNavigate = (viewId) => {
        setActiveView(viewId);
        setSidebarOpen(false);
    };

    if (!apiKey || authError) {
        return <SetupScreen healthResource={resources.health} error={authError} onSaveKey={handleSaveKey} />;
    }

    const activeNav = NAV_ITEMS.find((item) => item.id === activeView) ?? NAV_ITEMS[0];
    const settings = resources.settings.data?.settings ?? {};
    const planLabel = humanize(settings.plan_name ?? settings.plan ?? 'Plan unavailable');
    const health = resources.health.data ?? {};
    const isRefreshing = Object.values(resources).some((resource) => resource.loading);

    return (
        <div className="min-h-screen bg-ec-canvas text-ec-ink">
            {sidebarOpen && (
                <button
                    type="button"
                    aria-label="Close navigation"
                    className="fixed inset-0 z-30 bg-black/40 lg:hidden"
                    onClick={() => setSidebarOpen(false)}
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-ec-dark text-white transition-transform duration-200 lg:translate-x-0 ${
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex items-center gap-3 px-5 py-5">
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-ec-accent text-sm font-bold text-white">
                        EC
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-semibold">EuroCross AI</span>
                        <span className="block truncate text-[11px] text-white/60">Translation platform</span>
                    </span>
                    <button
                        type="button"
                        className="ml-auto rounded-md p-1 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
                        aria-label="Close navigation"
                        onClick={() => setSidebarOpen(false)}
                    >
                        <CloseIcon />
                    </button>
                </div>

                <nav aria-label="Primary" className="flex-1 px-3 py-2">
                    <ul className="flex flex-col gap-1">
                        {NAV_ITEMS.map((item) => {
                            const isActive = item.id === activeView;

                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleNavigate(item.id)}
                                        aria-current={isActive ? 'page' : undefined}
                                        className={`flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm transition-colors ${
                                            isActive
                                                ? 'bg-white/10 font-semibold text-white'
                                                : 'text-white/70 hover:bg-white/10 hover:text-white'
                                        }`}
                                    >
                                        <item.Icon />
                                        <span>{item.label}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="border-t border-white/10 px-5 py-4 text-[11px] text-white/60">
                    <p>API {health.status ?? '—'}{health.version ? ` · v${String(health.version)}` : ''}</p>
                    {health.demo_mode === true && <p className="mt-1 text-ec-accent">Demo mode enabled</p>}
                </div>
            </aside>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 border-b border-ec-line/60 bg-white/90 backdrop-blur">
                    <div className="flex items-center gap-3 px-4 py-3 sm:px-6">
                        <button
                            type="button"
                            className="rounded-lg border border-ec-line p-1.5 text-ec-primary hover:bg-ec-canvas lg:hidden"
                            aria-label="Open navigation"
                            aria-expanded={sidebarOpen}
                            onClick={() => setSidebarOpen(true)}
                        >
                            <MenuIcon />
                        </button>

                        <div className="min-w-0">
                            <p className="truncate text-[11px] font-medium uppercase tracking-wider text-ec-muted">EuroCross AI</p>
                            <p className="truncate text-sm font-semibold text-ec-dark">{activeNav.label}</p>
                        </div>

                        <div className="ml-auto flex shrink-0 items-center gap-2">
                            <span className="hidden rounded-full bg-ec-canvas px-3 py-1.5 text-xs font-medium text-ec-primary ring-1 ring-ec-line/70 sm:inline-flex">
                                {planLabel}
                            </span>
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-ec-canvas px-3 py-1.5 text-xs font-medium text-ec-muted ring-1 ring-ec-line/70">
                                <span className="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                                API key active
                            </span>
                            <button
                                type="button"
                                className="rounded-lg border border-ec-line p-1.5 text-ec-primary hover:bg-ec-canvas"
                                aria-label="Refresh dashboard data"
                                onClick={refreshAll}
                            >
                                <RefreshIcon spinning={isRefreshing} />
                            </button>
                        </div>
                    </div>
                </header>

                <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {activeView === 'overview' && <OverviewView summaryResource={resources.summary} />}
                    {activeView === 'usage' && <UsageView analyticsResource={resources.analytics} />}
                    {activeView === 'billing' && <BillingView settingsResource={resources.settings} onRefresh={refreshAll} />}
                    {activeView === 'vat' && <VatView vatRatesResource={resources.vatRates} />}
                    {activeView === 'keys' && <ApiKeysView apiKeysResource={resources.apiKeys} onRefresh={refreshAll} />}
                    {activeView === 'chatbot' && <ChatbotView chatbotResource={resources.chatbot} />}
                    {activeView === 'settings' && (
                        <SettingsView settingsResource={resources.settings} onSaveKey={handleSaveKey} onRefresh={refreshAll} />
                    )}
                </main>

                <footer className="border-t border-ec-line/60 px-4 py-4 text-center text-xs text-ec-muted sm:px-6">
                    EuroCross AI · Built with Laravel and React
                </footer>
            </div>
        </div>
    );
}
