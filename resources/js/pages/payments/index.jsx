import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, Wallet } from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import CustomerFilterChip from '@/components/customer-filter-chip';

import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import PaymentReviewInfo from '@/components/payments/payment-review-info';
import RowActionButton from '@/components/row-action-button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime } from '@/lib/format';
import { show as customerShow } from '@/routes/customers';
import { show as invoiceShow } from '@/routes/invoices';
import { index as paymentsIndex, review } from '@/routes/payments';
/** @import { DataTableColumn } from '@/components/data-table' */
/** @import { UseDialogTargetReturn } from '@/hooks/use-dialog-target' */
/**
 * @import {
 *     CustomerReference,
 *     Paginated,
 *     Payment,
 *     PaymentMethod,
 *     PaymentReviewStatus,
 *     SelectOption,
 * } from '@/types'
 */

/**
 * @typedef {object} PaymentFilters
 * @property {string} [search]
 * @property {string} [method]
 * @property {string} [review_status]
 * @property {string} [from]
 * @property {string} [to]
 * @property {string} [customer_id]
 * @property {string} [per_page]
 */

/**
 * @typedef {object} PaymentsIndexProps
 * @property {Paginated<Payment>} payments
 * @property {PaymentFilters} filters
 * @property {SelectOption<PaymentMethod>[]} methods
 * @property {SelectOption<PaymentReviewStatus>[]} review_statuses
 * @property {CustomerReference | null} customer
 */

const ALL = 'all';

const linkClass = 'font-medium text-primary underline-offset-4 hover:underline';

/**
 * Referensi yang bisa ditelusuri: order_id QRIS atau ID transaksi gateway.
 *
 * @param {Payment} payment
 * @returns {string | null}
 */
function paymentReference(payment) {
    return payment.order_id ?? payment.reference;
}

/**
 * QRIS dicatat gateway, bukan kasir.
 *
 * @param {Payment} payment
 * @returns {string}
 */
function receivedByText(payment) {
    if (payment.received_by) {
        return payment.received_by.name;
    }

    return payment.method === 'qris' ? 'Otomatis (QRIS)' : '—';
}

/**
 * @param {Payment} payment
 * @returns {boolean}
 */
function needsReview(payment) {
    return payment.review_status === 'needs_review';
}

/** @param {{ payment: Payment }} props */
function InvoiceCell({ payment }) {
    const reference = paymentReference(payment);

    return (
        <div className="min-w-0">
            {payment.invoice ? (
                <Link
                    href={invoiceShow(payment.invoice.id)}
                    className={linkClass}
                >
                    {payment.invoice.number}
                </Link>
            ) : (
                '—'
            )}
            {reference ? (
                <span className="block font-mono text-xs break-all text-muted-foreground">
                    {reference}
                </span>
            ) : null}
        </div>
    );
}

/** @param {{ payment: Payment }} props */
function CustomerCell({ payment }) {
    const customer = payment.invoice?.customer;

    if (!customer) {
        return '—';
    }

    return (
        <div className="min-w-0">
            <Link
                href={customerShow(customer.id)}
                className="font-medium hover:underline"
            >
                {customer.name}
            </Link>
            <span className="block font-mono text-xs text-muted-foreground">
                {customer.code}
            </span>
        </div>
    );
}

/** @param {PaymentsIndexProps} props */
export default function PaymentsIndex({
    payments,
    filters: initialFilters,
    methods,
    review_statuses: reviewStatuses,
    customer,
}) {
    const can = useCan();
    const canReview = can('payments.review');
    const filters = useFilters(initialFilters);
    /** @type {UseDialogTargetReturn<Payment>} */
    const dialog = useDialogTarget();
    const reviewing = dialog.item;

    /**
     * @param {Payment} payment
     * @param {boolean} [showLabel]
     */
    const reviewButton = (payment, showLabel = false) =>
        canReview && needsReview(payment) ? (
            <RowActionButton
                label="Tandai selesai ditinjau"
                icon={ClipboardCheck}
                showLabel={showLabel}
                className={showLabel ? 'w-full' : undefined}
                onClick={() => dialog.show(payment)}
            />
        ) : null;

    /** @type {DataTableColumn<Payment>[]} */
    const columns = [
        {
            key: 'paid_at',
            header: 'Tanggal bayar',
            className: 'whitespace-nowrap',
            cell: (payment) => formatDateTime(payment.paid_at),
        },
        {
            key: 'invoice',
            header: 'Tagihan',
            cell: (payment) => <InvoiceCell payment={payment} />,
        },
        {
            key: 'customer',
            header: 'Pelanggan',
            cell: (payment) => <CustomerCell payment={payment} />,
        },
        {
            key: 'method',
            header: 'Metode',
            cell: (payment) => payment.method_label,
        },
        {
            key: 'amount',
            header: 'Nominal',
            align: 'right',
            cell: (payment) => <Money amount={payment.amount} />,
        },
        {
            key: 'received_by',
            header: 'Diterima oleh',
            cell: receivedByText,
        },
        {
            key: 'review',
            header: 'Tinjauan',
            className: 'max-w-64 whitespace-normal',
            cell: (payment) => <PaymentReviewInfo payment={payment} />,
        },
        ...(canReview
            ? [
                  {
                      key: 'actions',
                      header: '',
                      align: /** @type {const} */ ('right'),
                      hideOnMobile: true,
                      /** @param {Payment} payment */
                      cell: (payment) => reviewButton(payment),
                  },
              ]
            : []),
    ];

    /** @param {Payment} payment */
    const paymentCard = (payment) => (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-3">
                <span className="font-medium">
                    {formatDateTime(payment.paid_at)}
                </span>
                <Money amount={payment.amount} className="font-medium" />
            </div>
            <InvoiceCell payment={payment} />
            <CustomerCell payment={payment} />
            <p className="text-muted-foreground">
                {payment.method_label} · {receivedByText(payment)}
            </p>
            {payment.review_status === 'none' ? null : (
                <PaymentReviewInfo payment={payment} />
            )}
            {reviewButton(payment, true)}
        </div>
    );

    return (
        <>
            <Head title="Pembayaran" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pembayaran"
                    description="Pembayaran QRIS tercatat otomatis; tunai dan transfer dicatat dari detail tagihan."
                />

                {filters.filters.customer_id ? (
                    <CustomerFilterChip
                        prefix="Pembayaran milik"
                        customer={customer}
                        onClear={() =>
                            filters.setFilter('customer_id', undefined)
                        }
                    />
                ) : null}

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari nomor, pelanggan, referensi…"
                >
                    <div className="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:flex-wrap">
                        <Select
                            value={filters.filters.method ?? ALL}
                            onValueChange={(value) =>
                                filters.setFilter(
                                    'method',
                                    value === ALL ? undefined : value,
                                )
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-40"
                                aria-label="Metode pembayaran"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua metode
                                </SelectItem>
                                {methods.map((method) => (
                                    <SelectItem
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.filters.review_status ?? ALL}
                            onValueChange={(value) =>
                                filters.setFilter(
                                    'review_status',
                                    value === ALL ? undefined : value,
                                )
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-56"
                                aria-label="Status tinjauan"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua status tinjauan
                                </SelectItem>
                                {reviewStatuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Input
                            type="date"
                            value={filters.filters.from ?? ''}
                            max={filters.filters.to}
                            onChange={(event) =>
                                filters.setFilter(
                                    'from',
                                    event.target.value === ''
                                        ? undefined
                                        : event.target.value,
                                )
                            }
                            aria-label="Tanggal bayar dari"
                            title="Tanggal bayar dari"
                            className="h-10 w-full md:w-40"
                        />

                        <Input
                            type="date"
                            value={filters.filters.to ?? ''}
                            min={filters.filters.from}
                            onChange={(event) =>
                                filters.setFilter(
                                    'to',
                                    event.target.value === ''
                                        ? undefined
                                        : event.target.value,
                                )
                            }
                            aria-label="Tanggal bayar sampai"
                            title="Tanggal bayar sampai"
                            className="h-10 w-full md:w-40"
                        />
                    </div>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={payments.data}
                    rowKey={(payment) => payment.id}
                    mobileCard={paymentCard}
                    rowClassName={(payment) =>
                        needsReview(payment)
                            ? 'border-warning/40 bg-warning/5 hover:bg-warning/10'
                            : undefined
                    }
                    emptyState={
                        filters.hasActiveFilters ? (
                            <EmptyState
                                icon={Wallet}
                                title="Tidak ada pembayaran yang cocok"
                                description="Ubah kata kunci atau hapus filter."
                            />
                        ) : (
                            <EmptyState
                                icon={Wallet}
                                title="Belum ada pembayaran"
                                description="Pembayaran muncul di sini setelah pelanggan membayar QRIS atau kasir mencatat pembayaran dari detail tagihan."
                            />
                        )
                    }
                />

                <Pagination paginator={payments} />
            </div>

            {reviewing !== null ? (
                <ConfirmDialog
                    key={dialog.key}
                    open={dialog.open}
                    onOpenChange={dialog.onOpenChange}
                    title="Tandai selesai ditinjau?"
                    description={
                        <>
                            <span className="block">
                                {reviewing.invoice?.number ?? 'Tagihan'}
                                {reviewing.invoice?.customer
                                    ? ` · ${reviewing.invoice.customer.name}`
                                    : ''}{' '}
                                · <Money amount={reviewing.amount} />
                            </span>
                            <span className="mt-2 block rounded-md border bg-muted/40 p-2 whitespace-pre-line text-foreground">
                                {reviewing.review_note ??
                                    'Tanpa alasan anomali.'}
                            </span>
                            <span className="mt-2 block">
                                Pengembalian dana dilakukan manual di luar
                                aplikasi. Catatan Anda ditambahkan di bawah
                                alasan anomali.
                            </span>
                        </>
                    }
                    action={review(reviewing.id)}
                    reason={{
                        field: 'review_note',
                        label: 'Catatan tinjauan',
                        placeholder:
                            'Misalnya: dana dikembalikan via transfer (minimal 5 karakter)',
                    }}
                    confirmLabel="Tandai selesai ditinjau"
                />
            ) : null}
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Pembayaran',
            href: paymentsIndex(),
        },
    ],
};
