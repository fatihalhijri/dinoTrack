import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { Permission } from '@/types';

export type CanFn = (permission: Permission | Permission[]) => boolean;

/**
 * Cek permission dari `auth.permissions` untuk menyembunyikan menu dan tombol.
 * Array berarti cukup salah satu. Otorisasi tetap dilakukan backend.
 */
export function useCan(): CanFn {
    const { permissions } = usePage().props.auth;

    return useCallback(
        (permission) => {
            const required = Array.isArray(permission)
                ? permission
                : [permission];

            return required.some((item) => permissions.includes(item));
        },
        [permissions],
    );
}
