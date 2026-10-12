import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
/** @import { Permission } from '@/types' */

/** @typedef {(permission: Permission | Permission[]) => boolean} CanFn */

/**
 * Cek permission dari `auth.permissions` untuk menyembunyikan menu dan tombol.
 * Array berarti cukup salah satu. Otorisasi tetap dilakukan backend.
 *
 * @returns {CanFn}
 */
export function useCan() {
    const { permissions } = usePage().props.auth;

    return useCallback(
        /** @param {Permission | Permission[]} permission */
        (permission) => {
            const required = Array.isArray(permission)
                ? permission
                : [permission];

            return required.some((item) => permissions.includes(item));
        },
        [permissions],
    );
}
