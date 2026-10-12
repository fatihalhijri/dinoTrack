import { useState } from 'react';

/**
 * @template T
 * @typedef {object} UseDialogTargetReturn
 * @property {T | null} item Baris yang sedang diproses; tetap terisi selama animasi tutup agar isi dialog tidak berkedip.
 * @property {boolean} open
 * @property {number} key Berubah setiap dialog dibuka; dipakai sebagai `key` agar form dimulai ulang dari data terbaru.
 * @property {(item?: T | null) => void} show
 * @property {(open: boolean) => void} onOpenChange
 */

/**
 * State satu dialog yang dipakai bersama oleh semua baris tabel (ubah, hapus, nonaktifkan),
 * sehingga halaman tidak membuat satu form per baris.
 *
 * @template T
 * @returns {UseDialogTargetReturn<T>}
 */
export function useDialogTarget() {
    const [state, setState] = useState(
        /** @type {{ item: T | null; open: boolean; key: number }} */ ({
            item: null,
            open: false,
            key: 0,
        }),
    );

    /**
     * @param {T | null} [item]
     * @returns {void}
     */
    const show = (item = null) => {
        setState((previous) => ({ item, open: true, key: previous.key + 1 }));
    };

    /**
     * @param {boolean} open
     * @returns {void}
     */
    const onOpenChange = (open) => {
        setState((previous) => ({ ...previous, open }));
    };

    return { ...state, show, onOpenChange };
}
