import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';

/** @typedef {string | number | null | undefined} FilterValue */

/** @typedef {Record<string, FilterValue>} FilterValues */

/**
 * @template {FilterValues} T
 * @typedef {object} UseFiltersReturn
 * @property {T} filters
 * @property {<K extends keyof T & string>(
 *     key: K,
 *     value: T[K],
 *     options?: { debounce?: boolean },
 * ) => void} setFilter
 * @property {() => void} reset
 * @property {boolean} hasActiveFilters
 */

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Nilai kosong tidak dikirim agar URL tetap bersih; `page` selalu dibuang saat filter berubah.
 *
 * @param {FilterValues} filters
 * @returns {Record<string, string | number>}
 */
function toQuery(filters) {
    /** @type {Record<string, string | number>} */
    const query = {};

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
 *
 * @template {FilterValues} T
 * @param {T} initial
 * @returns {UseFiltersReturn<T>}
 */
export function useFilters(initial) {
    const { currentUrl } = useCurrentUrl();
    const [filters, setFilters] = useState(initial);
    const timer = useRef(
        /** @type {ReturnType<typeof setTimeout> | null} */ (null),
    );

    useEffect(
        () => () => {
            if (timer.current !== null) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    /**
     * @param {FilterValues} next
     * @returns {void}
     */
    const visit = (next) => {
        router.get(currentUrl, toQuery(next), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    /** @type {UseFiltersReturn<T>['setFilter']} */
    const setFilter = (key, value, options = {}) => {
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

    /** @returns {void} */
    const reset = () => {
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
