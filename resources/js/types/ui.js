/** @import { ReactNode } from 'react' */
/** @import { BreadcrumbItem } from '@/types/navigation' */

/**
 * @typedef {object} AppLayoutProps
 * @property {ReactNode} children
 * @property {BreadcrumbItem[]} [breadcrumbs]
 */

/** @typedef {'header' | 'sidebar'} AppVariant */

/**
 * @typedef {object} FlashToast
 * @property {'success' | 'info' | 'warning' | 'error'} type
 * @property {string} message
 */

/**
 * @typedef {object} AuthLayoutProps
 * @property {ReactNode} [children]
 * @property {string} [name]
 * @property {string} [title]
 * @property {string} [description]
 */

export {};
