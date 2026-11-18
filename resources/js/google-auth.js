import { initializeApp } from 'firebase/app';
import {
    GoogleAuthProvider,
    getAuth,
    getRedirectResult,
    signInWithCredential,
    signInWithPopup,
    signInWithRedirect,
} from 'firebase/auth';

const POPUP_FALLBACK_CODES = new Set([
    'auth/popup-blocked',
    'auth/operation-not-supported-in-this-environment',
]);

function configFromBody() {
    const body = document.body;

    return {
        apiKey: body.dataset.firebaseApiKey,
        authDomain: body.dataset.firebaseAuthDomain,
        projectId: body.dataset.firebaseProjectId,
        storageBucket: body.dataset.firebaseStorageBucket,
        messagingSenderId: body.dataset.firebaseMessagingSenderId,
        appId: body.dataset.firebaseAppId,
        measurementId: body.dataset.firebaseMeasurementId,
    };
}

export function googleErrorMessage(error) {
    const code = typeof error?.code === 'string' ? error.code : '';

    if (code === 'auth/unauthorized-domain') {
        return 'Google sign-in is blocked because this domain is not authorized in Firebase. Add it under Firebase Console → Authentication → Settings → Authorized domains.';
    }

    if (code === 'auth/operation-not-allowed') {
        return 'Google sign-in is not enabled in Firebase Authentication.';
    }

    if (code === 'auth/network-request-failed') {
        return 'Could not reach Google. Check your connection and try again.';
    }

    const message = typeof error?.message === 'string' && error.message !== ''
        ? error.message
        : 'Google sign-in failed. Please try again.';

    return code === '' ? message : `${message} (${code})`;
}

export function showGoogleError(error, element) {
    console.error('[EuroCross] Google sign-in failed:', error);

    if (! element) {
        return;
    }

    element.textContent = googleErrorMessage(error);
    element.hidden = false;
}

export function createGoogleAuth(options = {}) {
    const config = configFromBody();
    const onError = options.onError;
    let auth = null;

    if (config.apiKey && config.projectId) {
        try {
            auth = getAuth(initializeApp(config));
        } catch (error) {
            console.warn('[EuroCross] Firebase init failed:', error);
        }
    }

    function reportError(error) {
        if (typeof onError === 'function') {
            onError(error);
        } else {
            console.error('[EuroCross] Google sign-in failed:', error);
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

        const body = await response.text();
        let payload = {};

        try {
            payload = body === '' ? {} : JSON.parse(body);
        } catch (error) {
            payload = {};
        }

        if (! response.ok || ! payload.success) {
            throw new Error(payload.error ?? `Google sign-in failed (${response.status}).`);
        }

        window.location.href = payload.redirect ?? '/dashboard';
    }

    async function completeSignIn(result) {
        if (! result?.user) {
            throw new Error('Google did not return a signed-in account.');
        }

        const idToken = await result.user.getIdToken();

        await sendTokenToServer(idToken);
    }

    function handleRedirectResult() {
        if (! auth) {
            return Promise.resolve(null);
        }

        return getRedirectResult(auth)
            .then(async (result) => {
                if (result?.user) {
                    await completeSignIn(result);
                }
            })
            .catch((error) => reportError(error));
    }

    async function signInWithGoogle() {
        if (! auth) {
            window.location.href = '/auth/google';
            return;
        }

        const provider = new GoogleAuthProvider();

        try {
            const result = await signInWithPopup(auth, provider);
            await completeSignIn(result);
        } catch (error) {
            if (POPUP_FALLBACK_CODES.has(error?.code)) {
                await signInWithRedirect(auth, provider);
                return;
            }

            reportError(error);
            throw error;
        }
    }

    async function signInWithGoogleCredential(googleIdToken) {
        if (! auth) {
            throw new Error('Firebase is not configured.');
        }

        const credential = GoogleAuthProvider.credential(googleIdToken);
        const result = await signInWithCredential(auth, credential);

        await completeSignIn(result);
    }

    return {
        auth,
        handleRedirectResult,
        signInWithGoogle,
        signInWithGoogleCredential,
    };
}
