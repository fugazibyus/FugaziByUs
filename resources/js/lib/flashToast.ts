import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * One place for every toast:
 *  - success/info messages flashed by the server (Inertia::flash('toast', ...))
 *  - validation errors on any form
 *  - server errors and lost connections
 */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('error', (event) => {
        const errors = (event as CustomEvent).detail?.errors ?? {};
        const count = Object.keys(errors).length;
        const first = Object.values(errors)[0];

        toast.error(
            count === 1 && typeof first === 'string'
                ? first
                : 'Please check the highlighted fields.',
        );
    });

    router.on('httpException', (event) => {
        const status = (event as CustomEvent).detail?.response?.status;

        if (status === 429) {
            toast.error(
                'Too many attempts. Please wait a minute and try again.',
            );

            return;
        }

        if (status === 419) {
            toast.error('Your session expired. Please refresh the page.');

            return;
        }

        toast.error('Something went wrong on our side. Please try again.');
    });

    router.on('networkError', () => {
        toast.error('Can’t reach the server. Check your connection.');
    });
}
