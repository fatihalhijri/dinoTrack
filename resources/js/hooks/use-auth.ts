import { usePage } from '@inertiajs/react';
import type { AuthUser } from '@/types';

/**
 * User login untuk komponen di dalam layout yang membutuhkan login.
 * Halaman tamu (auth, errors) membaca `auth.user` langsung karena bisa null.
 */
export function useAuthUser(): AuthUser {
    const { auth } = usePage().props;

    if (auth.user === null) {
        throw new Error('useAuthUser() dipakai di halaman tanpa user login.');
    }

    return auth.user;
}
