/**
 * Titik impor tunggal untuk tipe bersama (`@import { Customer } from '@/types'`).
 */

/** @typedef {import('./auth').AuthUser} AuthUser */
/** @typedef {import('./auth').Auth} Auth */
/** @typedef {import('./auth').Passkey} Passkey */
/** @typedef {import('./auth').TwoFactorSetupData} TwoFactorSetupData */
/** @typedef {import('./auth').TwoFactorSecretKey} TwoFactorSecretKey */
/** @typedef {import('./models').Role} Role */
/** @typedef {import('./models').CustomerStatus} CustomerStatus */
/** @typedef {import('./models').IsolationReason} IsolationReason */
/** @typedef {import('./models').InvoiceStatus} InvoiceStatus */
/** @typedef {import('./models').PaymentMethod} PaymentMethod */
/** @typedef {import('./models').PaymentReviewStatus} PaymentReviewStatus */
/** @typedef {import('./models').PaymentChargeStatus} PaymentChargeStatus */
/** @typedef {import('./models').MessageStatus} MessageStatus */
/** @typedef {import('./models').MessageTemplateKey} MessageTemplateKey */
/**
 * @template {string} [T=string]
 * @typedef {import('./models').SelectOption<T>} SelectOption
 */
/** @typedef {import('./models').PackageOption} PackageOption */
/** @typedef {import('./models').RouterOption} RouterOption */
/** @typedef {import('./models').Package} Package */
/** @typedef {import('./models').Router} Router */
/** @typedef {import('./models').PackageSummary} PackageSummary */
/** @typedef {import('./models').Subscription} Subscription */
/** @typedef {import('./models').Customer} Customer */
/** @typedef {import('./models').InvoiceItem} InvoiceItem */
/** @typedef {import('./models').PaymentCharge} PaymentCharge */
/** @typedef {import('./models').Payment} Payment */
/** @typedef {import('./models').Invoice} Invoice */
/** @typedef {import('./models').MessageLog} MessageLog */
/** @typedef {import('./models').ActivityLog} ActivityLog */
/** @typedef {import('./models').User} User */
/** @typedef {import('./models').MessageTemplate} MessageTemplate */
/** @typedef {import('./models').DashboardSummary} DashboardSummary */
/** @typedef {import('./models').CustomerCounts} CustomerCounts */
/** @typedef {import('./models').CustomerReference} CustomerReference */
/** @typedef {import('./models').InvoiceReference} InvoiceReference */
/** @typedef {import('./models').BusinessIdentity} BusinessIdentity */
/** @typedef {import('./models').BusinessProfile} BusinessProfile */
/** @typedef {import('./models').BillingSettings} BillingSettings */
/** @typedef {import('./models').MonthlyRevenue} MonthlyRevenue */
/** @typedef {import('./models').OutstandingAgeBucket} OutstandingAgeBucket */
/** @typedef {import('./models').AgingBucketTotal} AgingBucketTotal */
/** @typedef {import('./models').CustomerMovement} CustomerMovement */
/** @typedef {import('./models').OutstandingInvoice} OutstandingInvoice */
/** @typedef {import('./navigation').BreadcrumbItem} BreadcrumbItem */
/** @typedef {import('./navigation').NavItem} NavItem */
/** @typedef {import('./navigation').NavGroup} NavGroup */
/** @typedef {import('./pagination').PaginationLink} PaginationLink */
/**
 * @template T
 * @typedef {import('./pagination').Paginated<T>} Paginated
 */
/** @typedef {import('./permissions').Permission} Permission */
/** @typedef {import('./ui').AppLayoutProps} AppLayoutProps */
/** @typedef {import('./ui').AppVariant} AppVariant */
/** @typedef {import('./ui').FlashToast} FlashToast */
/** @typedef {import('./ui').AuthLayoutProps} AuthLayoutProps */

export {};
