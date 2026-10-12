/** @import { InertiaLinkProps } from '@inertiajs/react' */
/** @import { LucideIcon } from 'lucide-react' */
/** @import { Permission } from '@/types/permissions' */

/**
 * @typedef {object} BreadcrumbItem
 * @property {string} title
 * @property {NonNullable<InertiaLinkProps['href']>} href
 */

/**
 * @typedef {object} NavItem
 * @property {string} title
 * @property {NonNullable<InertiaLinkProps['href']>} href
 * @property {LucideIcon | null} [icon]
 * @property {boolean} [isActive]
 * @property {Permission} [permission] Menu disembunyikan jika user tidak punya permission ini.
 * @property {boolean} [exact] Aktif hanya jika URL persis sama (bukan halaman turunan).
 */

/**
 * @typedef {object} NavGroup
 * @property {string} title
 * @property {NavItem[]} items
 */

export {};
