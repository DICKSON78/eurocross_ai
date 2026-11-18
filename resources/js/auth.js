import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { initializeApp } from 'firebase/app';
import { getAuth, GoogleAuthProvider, signInWithRedirect, getRedirectResult } from 'firebase/auth';

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

const body = document.body;
const config = {
    apiKey: body.dataset.firebaseApiKey,
    authDomain: body.dataset.firebaseAuthDomain,
    projectId: body.dataset.firebaseProjectId,
    storageBucket: body.dataset.firebaseStorageBucket,
    messagingSenderId: body.dataset.firebaseMessagingSenderId,
    appId: body.dataset.firebaseAppId,
    measurementId: body.dataset.firebaseMeasurementId,
};

let auth = null;

if (config.apiKey && config.projectId) {
    try {
        const app = initializeApp(config);
        auth = getAuth(app);
    } catch (error) {
        console.warn('[EuroCross] Firebase init failed:', error);
    }
}

async function sendTokenToServer(idToken) {
    const response = await fetch('/auth/firebase', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            Accept: 'application/json',
        },
        body: JSON.stringify({ id_token: idToken }),
    });

    const payload = await response.json();

    if (!response.ok || !payload.success) {
        throw new Error(payload.error ?? 'Google sign-in failed.');
    }

    window.location.href = payload.redirect ?? '/dashboard';
}

if (auth) {
    getRedirectResult(auth)
        .then(async (result) => {
            if (result?.user) {
                const idToken = await result.user.getIdToken();
                await sendTokenToServer(idToken);
            }
        })
        .catch((error) => console.error('[EuroCross] Google redirect sign-in failed:', error));
}

window.ecGoogleSignIn = async function () {
    const button = document.querySelector('[data-google-button]');
    const label = button?.querySelector('[data-google-label]');

    if (!auth) {
        window.location.href = '/auth/google';
        return;
    }

    try {
        if (label) label.textContent = 'Connecting to Google…';
        if (button) button.disabled = true;
        await signInWithRedirect(auth, new GoogleAuthProvider());
    } catch (error) {
        if (label) label.textContent = 'Continue with Google';
        if (button) button.disabled = false;
        console.error('[EuroCross] Google sign-in failed:', error);
        window.alert(error.message ?? 'Google sign-in failed. Please try again.');
    }
};
