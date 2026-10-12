/**
 * Daftar berhalaman dari `ResourceCollection` + `paginate()->withQueryString()`.
 *
 * @typedef {object} PaginationLink
 * @property {string | null} url
 * @property {string} label
 * @property {number | null} page
 * @property {boolean} active
 */

/**
 * @template T
 * @typedef {object} Paginated
 * @property {T[]} data
 * @property {{
 *     first: string | null;
 *     last: string | null;
 *     prev: string | null;
 *     next: string | null;
 * }} links
 * @property {{
 *     current_page: number;
 *     from: number | null;
 *     last_page: number;
 *     links: PaginationLink[];
 *     path: string;
 *     per_page: number;
 *     to: number | null;
 *     total: number;
 * }} meta
 */

export {};
