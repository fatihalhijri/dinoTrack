/**
 * Bentuk data dari API Resource di app/Http/Resources (lihat docs/08-kontrak-halaman.md).
 * Uang = integer rupiah. Tanggal = 'YYYY-MM-DD'; timestamp = ISO 8601 dengan zona waktu.
 * Field opsional (`?`) hanya ada jika relasinya dimuat controller.
 */

/** @typedef {'admin' | 'kasir' | 'teknisi'} Role */

/** @typedef {'pending' | 'active' | 'isolated' | 'terminated'} CustomerStatus */

/** @typedef {'overdue' | 'manual'} IsolationReason */

/** @typedef {'unpaid' | 'paid' | 'overdue' | 'cancelled'} InvoiceStatus */

/** @typedef {'qris' | 'cash' | 'transfer'} PaymentMethod */

/** @typedef {'none' | 'needs_review' | 'resolved'} PaymentReviewStatus */

/** @typedef {'pending' | 'settled' | 'expired' | 'failed'} PaymentChargeStatus */

/** @typedef {'queued' | 'sent' | 'failed'} MessageStatus */

/**
 * @typedef {(
 *     | 'invoice_issued'
 *     | 'reminder_before_due'
 *     | 'reminder_due'
 *     | 'isolated'
 *     | 'payment_received'
 * )} MessageTemplateKey
 */

/**
 * Opsi dropdown untuk enum: `[{ value, label }]`.
 *
 * @template {string} [T=string]
 * @typedef {object} SelectOption
 * @property {T} value
 * @property {string} label
 */

/**
 * @typedef {object} PackageOption
 * @property {number} id
 * @property {string} name
 * @property {string} speed_label
 * @property {number} price
 */

/**
 * @typedef {object} RouterOption
 * @property {number} id
 * @property {string} name
 */

/**
 * @typedef {object} Package
 * @property {number} id
 * @property {string} name
 * @property {string} speed_label
 * @property {number} price
 * @property {string} mikrotik_profile
 * @property {boolean} is_active
 * @property {string | null} description
 * @property {number} [subscriptions_count]
 */

/**
 * @typedef {object} Router
 * @property {number} id
 * @property {string} name
 * @property {string} host
 * @property {number} port
 * @property {string} username
 * @property {boolean} use_ssl
 * @property {string} isolation_profile
 * @property {boolean} is_active
 * @property {string | null} last_connected_at
 * @property {number} [customers_count]
 */

/**
 * @typedef {object} PackageSummary
 * @property {number} id
 * @property {string} name
 * @property {string} speed_label
 */

/**
 * @typedef {object} Subscription
 * @property {number} id
 * @property {PackageSummary} [package]
 * @property {PackageSummary | null} [next_package]
 * @property {number} price
 * @property {number} billing_day
 * @property {string | null} starts_at
 * @property {string | null} ends_at
 * @property {string | null} created_at
 */

/**
 * @typedef {object} Customer
 * @property {number} id
 * @property {string} code
 * @property {string} name
 * @property {string} phone
 * @property {string} address
 * @property {string | null} odp
 * @property {string | null} latitude
 * @property {string | null} longitude
 * @property {RouterOption} [router]
 * @property {string} pppoe_username
 * @property {CustomerStatus} status
 * @property {string} status_label
 * @property {IsolationReason | null} isolation_reason
 * @property {string | null} isolation_reason_label
 * @property {string | null} installed_at
 * @property {string | null} isolated_at
 * @property {string | null} terminated_at
 * @property {string | null} network_error_at
 * @property {string | null} network_error
 * @property {string | null} notes
 * @property {Subscription | null} [subscription]
 * @property {string | null} created_at
 */

/**
 * @typedef {object} InvoiceItem
 * @property {number} id
 * @property {string} description
 * @property {number} quantity
 * @property {number} unit_price
 * @property {number} amount
 */

/**
 * @typedef {object} PaymentCharge
 * @property {number} id
 * @property {number} attempt
 * @property {string} order_id
 * @property {number} amount
 * @property {PaymentChargeStatus} status
 * @property {string} status_label
 * @property {string | null} expires_at
 * @property {string | null} created_at
 */

/**
 * @typedef {object} Payment
 * @property {number} id
 * @property {{
 *     id: number;
 *     number: string;
 *     status: InvoiceStatus;
 *     customer: { id: number; code: string; name: string } | null;
 * }} [invoice]
 * @property {string | null} [order_id]
 * @property {PaymentMethod} method
 * @property {string} method_label
 * @property {number} amount
 * @property {string} paid_at
 * @property {string | null} reference
 * @property {{ id: number; name: string } | null} [received_by]
 * @property {string | null} notes
 * @property {PaymentReviewStatus} review_status
 * @property {string} review_status_label
 * @property {string | null} review_note
 * @property {string | null} created_at
 */

/**
 * @typedef {object} Invoice
 * @property {number} id
 * @property {string} number
 * @property {{
 *     id: number;
 *     code: string;
 *     name: string;
 *     status: CustomerStatus;
 * }} [customer]
 * @property {string} period_start
 * @property {string} period_end
 * @property {string} issued_at
 * @property {string} due_at
 * @property {number} subtotal
 * @property {number} discount
 * @property {number} penalty
 * @property {number} total
 * @property {InvoiceStatus} status
 * @property {string} status_label
 * @property {string | null} paid_at
 * @property {string | null} cancelled_at
 * @property {string | null} cancelled_reason
 * @property {InvoiceItem[]} [items]
 * @property {Payment[]} [payments]
 * @property {PaymentCharge[]} [payment_charges]
 */

/**
 * @typedef {object} MessageLog
 * @property {number} id
 * @property {number | null} invoice_id
 * @property {MessageTemplateKey | null} template_key
 * @property {string | null} template_label
 * @property {string} phone
 * @property {string} body
 * @property {MessageStatus} status
 * @property {string} status_label
 * @property {string | null} error
 * @property {string | null} sent_at
 * @property {string | null} created_at
 */

/**
 * @typedef {object} ActivityLog
 * @property {number} id
 * @property {string} action
 * @property {string} action_label Label Bahasa Indonesia; aksi tanpa label berisi nama aksi mentah.
 * @property {{ id: number; name: string } | null} [user]
 * @property {Record<string, unknown> | null} properties
 * @property {string | null} created_at
 */

/**
 * @typedef {object} User
 * @property {number} id
 * @property {string} name
 * @property {string} email
 * @property {Role | null} role
 * @property {string | null} role_label
 * @property {boolean} is_active
 * @property {string | null} deactivated_at
 * @property {string | null} created_at
 * @property {boolean} [can_delete] Hanya di `users/index`: akun belum punya jejak audit (pembayaran yang dicatat, activity log).
 */

/**
 * @typedef {object} MessageTemplate
 * @property {number} id
 * @property {MessageTemplateKey} key
 * @property {string} label
 * @property {string} body
 * @property {boolean} is_active
 * @property {string | null} updated_at
 */

/**
 * `App\Data\Reports\DashboardSummary::toArray()`, di-cache 5 menit (`generated_at`).
 *
 * @typedef {object} DashboardSummary
 * @property {number} revenue_this_month
 * @property {number} payments_this_month
 * @property {number} outstanding_amount
 * @property {number} outstanding_invoices
 * @property {number} active_customers
 * @property {number} isolated_customers
 * @property {number} pending_customers
 * @property {number} due_this_week_amount
 * @property {number} due_this_week_invoices
 * @property {number} payments_needing_review
 * @property {number} payments_needing_review_amount
 * @property {number} customers_with_network_error
 * @property {string} generated_at
 */

/**
 * Jumlah pelanggan per status (real-time) + pelanggan dengan galat router.
 *
 * @typedef {Record<CustomerStatus, number> & {
 *     network_error: number;
 * }} CustomerCounts
 */

/**
 * Pelanggan terpilih di filter daftar tagihan/pembayaran (prop `customer` `invoices/index` dan `payments/index`).
 *
 * @typedef {object} CustomerReference
 * @property {number} id
 * @property {string} code
 * @property {string} name
 */

/**
 * Tagihan yang dirujuk (misalnya pengganti hasil terbit ulang).
 *
 * @typedef {object} InvoiceReference
 * @property {number} id
 * @property {string} number
 */

/**
 * Identitas usaha untuk tampilan cetak; `name` berisi `APP_NAME` jika belum diisi.
 *
 * @typedef {object} BusinessIdentity
 * @property {string} name
 * @property {string | null} address
 * @property {string | null} whatsapp
 */

/**
 * Isian profil usaha di `settings/business`; null = belum diisi.
 *
 * @typedef {object} BusinessProfile
 * @property {string | null} name
 * @property {string | null} address
 * @property {string | null} whatsapp
 */

/**
 * Aturan tagihan di `settings/billing` (docs/04 "Pengaturan default").
 *
 * @typedef {object} BillingSettings
 * @property {number} due_days
 * @property {number} grace_days
 * @property {number} reminder_days_before
 * @property {boolean} prorate_first_month
 * @property {boolean} auto_isolate
 * @property {boolean} auto_activate
 */

/**
 * Pendapatan satu bulan (`App\Data\Reports\MonthlyRevenue`): pembayaran normal menurut `paid_at`.
 *
 * @typedef {object} MonthlyRevenue
 * @property {number} month 1–12
 * @property {Record<PaymentMethod, number>} by_method
 * @property {number} total
 * @property {number} payment_count
 */

/** @typedef {'0-7' | '8-30' | '31+'} OutstandingAgeBucket */

/**
 * Umur tunggakan per kelompok (`App\Data\Reports\AgingBucketTotal`).
 *
 * @typedef {object} AgingBucketTotal
 * @property {OutstandingAgeBucket} bucket
 * @property {string} label
 * @property {number} invoice_count
 * @property {number} amount
 */

/**
 * Pergerakan pelanggan dalam rentang tanggal inklusif (`App\Data\Reports\CustomerMovement`).
 *
 * @typedef {object} CustomerMovement
 * @property {string} from
 * @property {string} to
 * @property {number} new_customers
 * @property {number} terminated_customers
 * @property {number} isolated_customers
 */

/**
 * Baris daftar tunggakan (`OutstandingInvoiceResource`).
 *
 * @typedef {object} OutstandingInvoice
 * @property {number} id
 * @property {string} number
 * @property {number} customer_id
 * @property {string} customer_code
 * @property {string} customer_name
 * @property {CustomerStatus} customer_status
 * @property {string} customer_status_label
 * @property {string} due_at
 * @property {number} age_days
 * @property {number} total
 * @property {InvoiceStatus} status
 * @property {string} status_label
 */

export {};
