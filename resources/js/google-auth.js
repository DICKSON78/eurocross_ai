import { initializeApp } from 'firebase/app';
import {
    GoogleAuthProvider,
    getAuth,
    getRedirectResult,
    signInWithCredential,
    signInWithRedirect,
} from 'firebase/auth';

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

export function createGoogleAuth() {
    const config = configFromBody();
    let auth = null;

    if (config.apiKey && config.projectId) {
        try {
            auth = getAuth(initializeApp(config));
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

    function handleRedirectResult() {
        if (!auth) {
            return Promise.resolve(null);
        }

        return getRedirectResult(auth)
            .then(async (result) => {
                if (result?.user) {
                    const idToken = await result.user.getIdToken();
                    await sendTokenToServer(idToken);
                }
            })
            .catch((error) => console.error('[EuroCross] Google redirect sign-in failed:', error));
    }

    async function signInWithRedirectGoogle() {
        if (!auth) {
            window.location.href = '/auth/google';
            return;
        }

        await signInWithRedirect(auth, new GoogleAuthProvider());
    }

    async function signInWithGoogleCredential(googleIdToken) {
        if (!auth) {
            throw new Error('Firebase is not configured.');
        }

        const credential = GoogleAuthProvider.credential(googleIdToken);
        const result = await signInWithCredential(auth, credential);
        const idToken = await result.user.getIdToken();

        await sendTokenToServer(idToken);
    }

    return {
        auth,
        handleRedirectResult,
        signInWithRedirectGoogle,
        signInWithGoogleCredential,
    };
}
