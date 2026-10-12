import { clsx } from 'clsx';

import { twMerge } from 'tailwind-merge';
/** @import { InertiaLinkProps } from '@inertiajs/react' */
/** @import { ClassValue } from 'clsx' */

/** @param {ClassValue[]} inputs */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}

/**
 * @param {NonNullable<InertiaLinkProps['href']>} url
 * @returns {string}
 */
export function toUrl(url) {
    return typeof url === 'string' ? url : url.url;
}
