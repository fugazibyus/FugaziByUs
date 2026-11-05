import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SiteLayout from '@/layouts/SiteLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'FugaziByUs';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Password reset/confirm screens use the minimal auth layout; everything else
    // gets the site header, footer, login/register modals and toasts.
    layout: (name) => (name.startsWith('auth/') ? AuthLayout : SiteLayout),
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#7c3aed',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
