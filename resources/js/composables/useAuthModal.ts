import { ref } from 'vue';

export type AuthModal = 'login' | 'register';

// Shared across the whole app, so any button can open the modal.
const active = ref<AuthModal | null>(null);

function isAuthModal(value: string | null): value is AuthModal {
    return value === 'login' || value === 'register';
}

/**
 * Login/register live in modals. `?auth=login` or `?auth=register` in the URL
 * opens them, which is how /login, /register and "please log in" redirects work.
 */
export function useAuthModal() {
    function open(modal: AuthModal): void {
        active.value = modal;
    }

    function close(): void {
        active.value = null;
        removeQueryParam();
    }

    function openFromUrl(): void {
        if (typeof window === 'undefined') {
            return;
        }

        const value = new URLSearchParams(window.location.search).get('auth');

        if (isAuthModal(value)) {
            active.value = value;
        }
    }

    return { active, open, close, openFromUrl };
}

function removeQueryParam(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (!url.searchParams.has('auth')) {
        return;
    }

    url.searchParams.delete('auth');
    // Keep Inertia's history state; only tidy the address bar.
    window.history.replaceState(window.history.state, '', url.toString());
}
