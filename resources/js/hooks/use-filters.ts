import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';

export type FilterValue = string | number | null | undefined;

export type FilterValues = Record<string, FilterValue>;

export type UseFiltersReturn<T extends FilterValues> = {
    filters: T;
    setFilter: <K extends keyof T & string>(
        key: K,
        value: T[K],
        options?: { debounce?: boolean },
    ) => void;
    reset: () => void;
    hasActiveFilters: boolean;
};

const SEARCH_DEBOUNCE_MS = 300;

/** Nilai kosong tidak dikirim agar URL tetap bersih; `page` selalu dibuang saat filter berubah. */
function toQuery(filters: FilterValues): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (
            key !== 'page' &&
            value !== null &&
            value !== undefined &&
            value !== ''
        ) {
            query[key] = value;
        }
    }

    return query;
}

/**
 * State filter halaman daftar yang disinkronkan ke query string.
 * `initial` diisi dari prop `filters` backend. Pencarian (`search`) di-debounce.
 */
export function useFilters<T extends FilterValues>(
    initial: T,
): UseFiltersReturn<T> {
    const { currentUrl } = useCurrentUrl();
    const [filters, setFilters] = useState<T>(initial);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const visit = (next: FilterValues): void => {
        router.get(currentUrl, toQuery(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const setFilter: UseFiltersReturn<T>['setFilter'] = (
        key,
        value,
        options = {},
    ) => {
        const next = { ...filters, [key]: value };
        const debounce = options.debounce ?? key === 'search';

        setFilters(next);

        if (timer.current !== null) {
            clearTimeout(timer.current);
        }

        if (debounce) {
            timer.current = setTimeout(() => visit(next), SEARCH_DEBOUNCE_MS);
        } else {
            visit(next);
        }
    };

    const reset = (): void => {
        const cleared = Object.fromEntries(
            Object.keys(filters).map((key) => [key, undefined]),
        );

        setFilters({ ...filters, ...cleared, per_page: filters.per_page });
        visit({ per_page: filters.per_page });
    };

    const hasActiveFilters = Object.entries(filters).some(
        ([key, value]) =>
            key !== 'per_page' &&
            key !== 'page' &&
            value !== null &&
            value !== undefined &&
            value !== '',
    );

    return { filters, setFilter, reset, hasActiveFilters };
}
