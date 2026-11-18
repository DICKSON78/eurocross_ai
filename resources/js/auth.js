import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { createGoogleAuth, showGoogleError } from './google-auth';

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

const googleAuth = createGoogleAuth({
    onError: (error) => showGoogleError(error, document.querySelector('[data-google-error]')),
});

googleAuth.handleRedirectResult();

window.ecGoogleSignIn = async function () {
    const button = document.querySelector('[data-google-button]');
    const label = button?.querySelector('[data-google-label]');
    const error = document.querySelector('[data-google-error]');

    if (error) {
        error.hidden = true;
        error.textContent = '';
    }

    try {
        if (label) label.textContent = 'Connecting to Google…';
        if (button) button.disabled = true;
        await googleAuth.signInWithGoogle();
    } catch (error) {
        if (label) label.textContent = 'Continue with Google';
        if (button) button.disabled = false;
    }
};
