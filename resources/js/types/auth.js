/** @import { Role } from '@/types/models' */
/** @import { Permission } from '@/types/permissions' */

/**
 * User login dari props bersama `auth.user` (HandleInertiaRequests::userData).
 *
 * @typedef {object} AuthUser
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {Role | null} role
 * @property {string | null} role_label
 * @property {string | null} email_verified_at
 * @property {boolean} two_factor_enabled
 */

/**
 * @typedef {object} Auth
 * @property {AuthUser | null} user
 * @property {Permission[]} permissions
 */

/**
 * @typedef {object} Passkey
 * @property {number} id
 * @property {string} name
 * @property {string | null} authenticator
 * @property {string} created_at_diff
 * @property {string | null} last_used_at_diff
 */

/**
 * @typedef {object} TwoFactorSetupData
 * @property {string} svg
 * @property {string} url
 */

/**
 * @typedef {object} TwoFactorSecretKey
 * @property {string} secretKey
 */

export {};
