export const API_BASE = '/eurocross';
export const API_KEY_STORAGE_KEY = 'eurocross_api_key';
export const ISSUE_KEY_COMMAND = 'php artisan app:issue-euro-cross-api-key';
export const EMPTY_VALUE = '—';

const REQUEST_TIMEOUT_MS = 15000;
const STATUS_MESSAGES = {
    401: 'Your API key is missing or invalid. Update it in Settings.',
    403: 'Your plan does not allow this action.',
    429: 'Too many requests. Please wait a moment and try again.',
};

function browserStorage() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

export function readApiKey() {
    const storage = browserStorage();
    if (!storage) {
        return '';
    }

    try {
        return (storage.getItem(API_KEY_STORAGE_KEY) || '').trim();
    } catch {
        return '';
    }
}

export function writeApiKey(value) {
    const key = String(value ?? '').trim();
    if (!key) {
        throw new Error('Paste an API key before saving.');
    }

    const storage = browserStorage();
    if (!storage) {
        throw new Error('Local storage is unavailable in this browser.');
    }

    storage.setItem(API_KEY_STORAGE_KEY, key);
    return key;
}

export function removeApiKey() {
    const storage = browserStorage();
    if (!storage) {
        return;
    }

    try {
        storage.removeItem(API_KEY_STORAGE_KEY);
    } catch {
        // Ignore storage failures; the in-memory state is still cleared.
    }
}

export function currentApiKey() {
    return readApiKey() || String(import.meta.env.VITE_EUROCROSS_API_KEY || '').trim();
}

function payloadMessage(payload) {
    if (!payload || typeof payload !== 'object') {
        return '';
    }

    if (typeof payload.message === 'string' && payload.message.trim()) {
        return payload.message.trim();
    }

    if (typeof payload.error === 'string' && payload.error.trim()) {
        return payload.error.trim();
    }

    const errors = payload.errors;
    if (errors && typeof errors === 'object') {
        const firstError = Object.values(errors)[0];
        if (Array.isArray(firstError) && firstError.length > 0) {
            return String(firstError[0]);
        }
        if (typeof firstError === 'string') {
            return firstError;
        }
    }

    return '';
}

function apiError(status, payload) {
    const detail = payloadMessage(payload);
    const message = STATUS_MESSAGES[status] || detail || `Request failed (${status || 'network'}).`;
    const error = new Error(message);
    error.status = status;
    error.payload = payload;
    return error;
}

export async function api(path, options = {}) {
    const {
        method = 'GET',
        body,
        auth = true,
        signal,
        timeoutMs = REQUEST_TIMEOUT_MS,
    } = options;

    const url = `${API_BASE}${path.startsWith('/') ? path : `/${path}`}`;
    const controller = new AbortController();
    let timedOut = false;
    const timer = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs);

    const forwardAbort = () => controller.abort();
    if (signal) {
        if (signal.aborted) {
            controller.abort();
        } else {
            signal.addEventListener('abort', forwardAbort, { once: true });
        }
    }

    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const apiKey = currentApiKey();
    if (auth && apiKey) {
        headers['X-API-Key'] = apiKey;
    }

    try {
        const response = await fetch(url, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: controller.signal,
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }

        if (!response.ok || (payload && payload.success === false)) {
            throw apiError(response.status, payload);
        }

        return payload ?? {};
    } catch (error) {
        if (error?.name === 'AbortError' && timedOut) {
            const timeoutError = new Error('The request timed out. Please check your connection and try again.');
            timeoutError.status = 0;
            throw timeoutError;
        }

        if (error instanceof TypeError) {
            const networkError = new Error('Unable to reach the server. Check your connection and try again.');
            networkError.status = 0;
            throw networkError;
        }

        throw error;
    } finally {
        clearTimeout(timer);
        if (signal) {
            signal.removeEventListener('abort', forwardAbort);
        }
    }
}

export function formatCurrency(value, currency = 'EUR') {
    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return EMPTY_VALUE;
    }

    return new Intl.NumberFormat('en-IE', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function formatNumber(value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return EMPTY_VALUE;
    }

    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(amount);
}

export function formatDecimal(value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return EMPTY_VALUE;
    }

    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(amount);
}

export function formatPercent(value) {
    const amount = Number(value);
    if (!Number.isFinite(amount)) {
        return EMPTY_VALUE;
    }

    return `${formatDecimal(amount)}%`;
}

export function formatDate(value, includeTime = false) {
    if (!value) {
        return EMPTY_VALUE;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(includeTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(date);
}

export function humanize(value) {
    if (value === null || value === undefined || value === '') {
        return EMPTY_VALUE;
    }

    return String(value)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function extractText(payload, keys = ['reply', 'message', 'response', 'answer']) {
    const data = payload?.data ?? payload;

    if (typeof data === 'string') {
        return data;
    }

    if (data && typeof data === 'object') {
        for (const key of keys) {
            if (typeof data[key] === 'string' && data[key].trim()) {
                return data[key].trim();
            }
        }
    }

    return '';
}
