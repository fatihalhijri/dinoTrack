/**
 * Bentuk data dari API Resource di app/Http/Resources (lihat docs/08-kontrak-halaman.md).
 * Uang = integer rupiah. Tanggal = 'YYYY-MM-DD'; timestamp = ISO 8601 dengan zona waktu.
 * Field opsional (`?`) hanya ada jika relasinya dimuat controller.
 */

export type Role = 'admin' | 'kasir' | 'teknisi';
export type CustomerStatus = 'pending' | 'active' | 'isolated' | 'terminated';
export type IsolationReason = 'overdue' | 'manual';
export type InvoiceStatus = 'unpaid' | 'paid' | 'overdue' | 'cancelled';
export type PaymentMethod = 'qris' | 'cash' | 'transfer';
export type PaymentReviewStatus = 'none' | 'needs_review' | 'resolved';
export type PaymentChargeStatus = 'pending' | 'settled' | 'expired' | 'failed';
export type MessageStatus = 'queued' | 'sent' | 'failed';
export type MessageTemplateKey =
    | 'invoice_issued'
    | 'reminder_before_due'
    | 'reminder_due'
    | 'isolated'
    | 'payment_received';

/** Opsi dropdown untuk enum: `[{ value, label }]`. */
export type SelectOption<T extends string = string> = {
    value: T;
    label: string;
};

export type PackageOption = {
    id: number;
    name: string;
    speed_label: string;
    price: number;
};

export type RouterOption = {
    id: number;
    name: string;
};

export type Package = {
    id: number;
    name: string;
    speed_label: string;
    price: number;
    mikrotik_profile: string;
    is_active: boolean;
    description: string | null;
    subscriptions_count?: number;
};

export type Router = {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    use_ssl: boolean;
    isolation_profile: string;
    is_active: boolean;
    last_connected_at: string | null;
    customers_count?: number;
};

export type PackageSummary = {
    id: number;
    name: string;
    speed_label: string;
};

export type Subscription = {
    id: number;
    package?: PackageSummary;
    next_package?: PackageSummary | null;
    price: number;
    billing_day: number;
    starts_at: string | null;
    ends_at: string | null;
};

export type Customer = {
    id: number;
    code: string;
    name: string;
    phone: string;
    address: string;
    odp: string | null;
    latitude: string | null;
    longitude: string | null;
    router?: RouterOption;
    pppoe_username: string;
    status: CustomerStatus;
    status_label: string;
    isolation_reason: IsolationReason | null;
    isolation_reason_label: string | null;
    installed_at: string | null;
    isolated_at: string | null;
    terminated_at: string | null;
    network_error_at: string | null;
    network_error: string | null;
    notes: string | null;
    subscription?: Subscription | null;
    created_at: string | null;
};

export type InvoiceItem = {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
};

export type PaymentCharge = {
    id: number;
    attempt: number;
    order_id: string;
    amount: number;
    status: PaymentChargeStatus;
    status_label: string;
    expires_at: string | null;
    created_at: string | null;
};

export type Payment = {
    id: number;
    invoice?: {
        id: number;
        number: string;
        status: InvoiceStatus;
        customer: { id: number; code: string; name: string } | null;
    };
    order_id?: string | null;
    method: PaymentMethod;
    method_label: string;
    amount: number;
    paid_at: string;
    reference: string | null;
    received_by?: { id: number; name: string } | null;
    notes: string | null;
    review_status: PaymentReviewStatus;
    review_status_label: string;
    review_note: string | null;
    created_at: string | null;
};

export type Invoice = {
    id: number;
    number: string;
    customer?: {
        id: number;
        code: string;
        name: string;
        status: CustomerStatus;
    };
    period_start: string;
    period_end: string;
    issued_at: string;
    due_at: string;
    subtotal: number;
    discount: number;
    penalty: number;
    total: number;
    status: InvoiceStatus;
    status_label: string;
    paid_at: string | null;
    cancelled_at: string | null;
    cancelled_reason: string | null;
    items?: InvoiceItem[];
    payments?: Payment[];
    payment_charges?: PaymentCharge[];
};

export type MessageLog = {
    id: number;
    invoice_id: number | null;
    template_key: MessageTemplateKey | null;
    template_label: string | null;
    phone: string;
    body: string;
    status: MessageStatus;
    status_label: string;
    error: string | null;
    sent_at: string | null;
    created_at: string | null;
};

export type ActivityLog = {
    id: number;
    action: string;
    /** Label Bahasa Indonesia; aksi tanpa label berisi nama aksi mentah. */
    action_label: string;
    user?: { id: number; name: string } | null;
    properties: Record<string, unknown> | null;
    created_at: string | null;
};

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    role_label: string | null;
    is_active: boolean;
    deactivated_at: string | null;
    created_at: string | null;
};

export type MessageTemplate = {
    id: number;
    key: MessageTemplateKey;
    label: string;
    body: string;
    is_active: boolean;
    updated_at: string | null;
};

/** `App\Data\Reports\DashboardSummary::toArray()`, di-cache 5 menit (`generated_at`). */
export type DashboardSummary = {
    revenue_this_month: number;
    payments_this_month: number;
    outstanding_amount: number;
    outstanding_invoices: number;
    active_customers: number;
    isolated_customers: number;
    pending_customers: number;
    due_this_week_amount: number;
    due_this_week_invoices: number;
    payments_needing_review: number;
    payments_needing_review_amount: number;
    customers_with_network_error: number;
    generated_at: string;
};

/** Jumlah pelanggan per status (real-time) + pelanggan dengan galat router. */
export type CustomerCounts = Record<CustomerStatus, number> & {
    network_error: number;
};

/** Pelanggan terpilih di filter daftar tagihan/pembayaran (prop `customer` `invoices/index` dan `payments/index`). */
export type CustomerReference = {
    id: number;
    code: string;
    name: string;
};

/** Tagihan yang dirujuk (misalnya pengganti hasil terbit ulang). */
export type InvoiceReference = {
    id: number;
    number: string;
};

/** Identitas usaha untuk tampilan cetak; `name` berisi `APP_NAME` jika belum diisi. */
export type BusinessIdentity = {
    name: string;
    address: string | null;
    whatsapp: string | null;
};

/** Pendapatan satu bulan (`App\Data\Reports\MonthlyRevenue`): pembayaran normal menurut `paid_at`. */
export type MonthlyRevenue = {
    /** 1–12 */
    month: number;
    by_method: Record<PaymentMethod, number>;
    total: number;
    payment_count: number;
};

export type OutstandingAgeBucket = '0-7' | '8-30' | '31+';

/** Umur tunggakan per kelompok (`App\Data\Reports\AgingBucketTotal`). */
export type AgingBucketTotal = {
    bucket: OutstandingAgeBucket;
    label: string;
    invoice_count: number;
    amount: number;
};

/** Pergerakan pelanggan dalam rentang tanggal inklusif (`App\Data\Reports\CustomerMovement`). */
export type CustomerMovement = {
    from: string;
    to: string;
    new_customers: number;
    terminated_customers: number;
    isolated_customers: number;
};

/** Baris daftar tunggakan (`OutstandingInvoiceResource`). */
export type OutstandingInvoice = {
    id: number;
    number: string;
    customer_id: number;
    customer_code: string;
    customer_name: string;
    customer_status: CustomerStatus;
    customer_status_label: string;
    due_at: string;
    age_days: number;
    total: number;
    status: InvoiceStatus;
    status_label: string;
};
