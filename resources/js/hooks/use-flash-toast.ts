import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

const TOO_MANY_REQUESTS = 429;

/**
 * Toast dari flash Inertia, dan dari respons 429 route yang di-throttle (misalnya kirim ulang
 * tagihan). Respons 429 berupa halaman HTML, sehingga tanpa ini Inertia menampilkannya di modal.
 */
export function useFlashToast(): void {
    useEffect(() => {
        const removeFlashListener = router.on('flash', (event) => {
            const data = event.detail.flash.toast;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });

        const removeHttpExceptionListener = router.on(
            'httpException',
            (event) => {
                if (event.detail.response.status !== TOO_MANY_REQUESTS) {
                    return;
                }

                toast.error(
                    'Terlalu banyak permintaan. Coba lagi dalam satu menit.',
                );

                return false;
            },
        );

        return () => {
            removeFlashListener();
            removeHttpExceptionListener();
        };
    }, []);
}
