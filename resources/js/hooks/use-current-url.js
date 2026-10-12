import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';
/** @import { InertiaLinkProps } from '@inertiajs/react' */

/**
 * @typedef {(
 *     urlToCheck: NonNullable<InertiaLinkProps['href']>,
 *     currentUrl?: string,
 *     startsWith?: boolean,
 * ) => boolean} IsCurrentUrlFn
 */

/**
 * @typedef {(
 *     urlToCheck: NonNullable<InertiaLinkProps['href']>,
 *     currentUrl?: string,
 * ) => boolean} IsCurrentOrParentUrlFn
 */

/**
 * @typedef {<TIfTrue, TIfFalse = null>(
 *     urlToCheck: NonNullable<InertiaLinkProps['href']>,
 *     ifTrue: TIfTrue,
 *     ifFalse?: TIfFalse,
 * ) => TIfTrue | TIfFalse} WhenCurrentUrlFn
 */

/**
 * @typedef {object} UseCurrentUrlReturn
 * @property {string} currentUrl
 * @property {IsCurrentUrlFn} isCurrentUrl
 * @property {IsCurrentOrParentUrlFn} isCurrentOrParentUrl
 * @property {WhenCurrentUrlFn} whenCurrentUrl
 */

/** @returns {UseCurrentUrlReturn} */
export function useCurrentUrl() {
    const page = usePage();
    const currentUrlPath = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    ).pathname;

    /** @type {IsCurrentUrlFn} */
    const isCurrentUrl = (urlToCheck, currentUrl, startsWith = false) => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const urlString = toUrl(urlToCheck);

        /**
         * @param {string} path
         * @returns {boolean}
         */
        const comparePath = (path) =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    };

    /** @type {IsCurrentOrParentUrlFn} */
    const isCurrentOrParentUrl = (urlToCheck, currentUrl) => {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    };

    /**
     * @template TIfTrue
     * @template [TIfFalse=null]
     * @param {NonNullable<InertiaLinkProps['href']>} urlToCheck
     * @param {TIfTrue} ifTrue
     * @param {TIfFalse} [ifFalse]
     * @returns {TIfTrue | TIfFalse}
     */
    const whenCurrentUrl = (
        urlToCheck,
        ifTrue,
        ifFalse = /** @type {TIfFalse} */ (null),
    ) => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
