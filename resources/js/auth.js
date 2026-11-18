import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createGoogleAuth } from './google-auth';

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

const googleAuth = createGoogleAuth();

googleAuth.handleRedirectResult();

window.ecGoogleSignIn = async function () {
    const button = document.querySelector('[data-google-button]');
    const label = button?.querySelector('[data-google-label]');

    try {
        if (label) label.textContent = 'Connecting to Google…';
        if (button) button.disabled = true;
        await googleAuth.signInWithRedirectGoogle();
    } catch (error) {
        if (label) label.textContent = 'Continue with Google';
        if (button) button.disabled = false;
        console.error('[EuroCross] Google sign-in failed:', error);
        window.alert(error.message ?? 'Google sign-in failed. Please try again.');
    }
};
