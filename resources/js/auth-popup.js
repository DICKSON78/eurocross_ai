import { createGoogleAuth } from './google-auth';

const STORAGE_KEY = 'ecAuthPopupSeen';
const popup = document.querySelector('[data-auth-popup]');
const googleAuth = createGoogleAuth();

let oneTapDisplayed = false;
let previousOverflow = '';

googleAuth.handleRedirectResult();

function markSeen() {
    try {
        sessionStorage.setItem(STORAGE_KEY, '1');
    } catch (error) {
        // Session storage may be unavailable in private browsing.
    }
}

function wasSeen() {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch (error) {
        return false;
    }
}

function openPopup() {
    if (!popup || !popup.classList.contains('hidden')) {
        return;
    }

    markSeen();
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    popup.classList.remove('hidden');
    popup.classList.add('flex');
    popup.querySelector('[data-auth-popup-google]')?.focus();
}

function closePopup() {
    if (!popup) {
        return;
    }

    popup.classList.add('hidden');
    popup.classList.remove('flex');
    document.body.style.overflow = previousOverflow;
}

async function startGoogleSignIn() {
    const button = popup?.querySelector('[data-auth-popup-google]');
    const label = popup?.querySelector('[data-auth-popup-google-label]');

    try {
        if (button) button.disabled = true;
        if (label) label.textContent = 'Connecting to Google…';
        await googleAuth.signInWithRedirectGoogle();
    } catch (error) {
        if (button) button.disabled = false;
        if (label) label.textContent = 'Continue with Google';
        console.error('[EuroCross] Google sign-in failed:', error);
        window.alert(error.message ?? 'Google sign-in failed. Please try again.');
    }
}

async function handleOneTapCredential(credential) {
    if (!credential) {
        return;
    }

    markSeen();

    try {
        await googleAuth.signInWithGoogleCredential(credential);
    } catch (error) {
        console.error('[EuroCross] Google One Tap sign-in failed:', error);
        openPopup();
    }
}

function startOneTap() {
    const clientId = document.body.dataset.googleClientId;

    if (!clientId || !window.google?.accounts?.id) {
        return false;
    }

    window.google.accounts.id.initialize({
        client_id: clientId,
        callback: (response) => handleOneTapCredential(response?.credential),
        context: 'signup',
        ux_mode: 'popup',
        auto_select: false,
        cancel_on_tap_outside: true,
        itp_support: true,
    });

    window.google.accounts.id.prompt((notification) => {
        if (notification.isDisplayed?.()) {
            oneTapDisplayed = true;
            markSeen();
            closePopup();

            return;
        }

        if (notification.isNotDisplayed?.() || notification.isSkippedMoment?.()) {
            openPopup();
        }
    });

    return true;
}

function boot() {
    if (!popup || wasSeen()) {
        return;
    }

    document.querySelectorAll('[data-auth-popup-close]').forEach((button) => {
        button.addEventListener('click', closePopup);
    });

    popup.querySelector('[data-auth-popup-google]')?.addEventListener('click', startGoogleSignIn);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePopup();
        }
    });

    const oneTapStarted = startOneTap();

    window.setTimeout(() => {
        if (!oneTapStarted || !oneTapDisplayed) {
            openPopup();
        }
    }, 1200);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
