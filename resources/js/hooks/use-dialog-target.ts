import { useState } from 'react';

export type UseDialogTargetReturn<T> = {
    /** Baris yang sedang diproses; tetap terisi selama animasi tutup agar isi dialog tidak berkedip. */
    item: T | null;
    open: boolean;
    /** Berubah setiap dialog dibuka; dipakai sebagai `key` agar form dimulai ulang dari data terbaru. */
    key: number;
    show: (item?: T | null) => void;
    onOpenChange: (open: boolean) => void;
};

/**
 * State satu dialog yang dipakai bersama oleh semua baris tabel (ubah, hapus, nonaktifkan),
 * sehingga halaman tidak membuat satu form per baris.
 */
export function useDialogTarget<T>(): UseDialogTargetReturn<T> {
    const [state, setState] = useState<{
        item: T | null;
        open: boolean;
        key: number;
    }>({ item: null, open: false, key: 0 });

    const show = (item: T | null = null): void => {
        setState((previous) => ({ item, open: true, key: previous.key + 1 }));
    };

    const onOpenChange = (open: boolean): void => {
        setState((previous) => ({ ...previous, open }));
    };

    return { ...state, show, onOpenChange };
}
