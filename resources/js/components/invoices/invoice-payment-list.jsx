import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import Money from '@/components/money';
import PaymentReviewInfo from '@/components/payments/payment-review-info';
import { formatDateTime } from '@/lib/format';
/** @import { DataTableColumn } from '@/components/data-table' */
/** @import { Payment } from '@/types' */

/** Referensi yang bisa ditelusuri: order_id QRIS atau ID transaksi gateway. *
 * @param {Payment} payment
 * @returns {string | null}
 */
function paymentReference(payment) {
    return payment.order_id ?? payment.reference;
}

/**
 * @param {{ payment: Payment }} props
 */
function MethodText({ payment }) {
    return (
        <span>
            {payment.method_label}
            {payment.received_by ? (
                <span className="block text-xs text-muted-foreground">
                    oleh {payment.received_by.name}
                </span>
            ) : null}
        </span>
    );
}

/**
 * @param {{ payment: Payment }} props
 */
function PaymentNotes({ payment }) {
    return payment.notes ? (
        <p className="text-xs whitespace-pre-line text-muted-foreground">
            {payment.notes}
        </p>
    ) : null;
}

/** @type {DataTableColumn<Payment>[]} */
const columns = [
    {
        key: 'paid_at',
        header: 'Tanggal bayar',
        cell: (payment) => formatDateTime(payment.paid_at),
    },
    {
        key: 'method',
        header: 'Metode',
        cell: (payment) => (
            <div className="grid gap-1">
                <MethodText payment={payment} />
                <PaymentNotes payment={payment} />
            </div>
        ),
    },
    {
        key: 'reference',
        header: 'Referensi',
        cell: (payment) => (
            <span className="font-mono text-xs break-all">
                {paymentReference(payment) ?? '—'}
            </span>
        ),
    },
    {
        key: 'amount',
        header: 'Nominal',
        align: 'right',
        cell: (payment) => <Money amount={payment.amount} />,
    },
    {
        key: 'review',
        header: 'Tinjauan',
        className: 'max-w-64 whitespace-normal',
        cell: (payment) => <PaymentReviewInfo payment={payment} />,
    },
];

/** @param {Payment} payment */
function PaymentCard(payment) {
    const reference = paymentReference(payment);

    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-2">
                <span className="font-medium">
                    {formatDateTime(payment.paid_at)}
                </span>
                <Money amount={payment.amount} className="font-medium" />
            </div>
            <MethodText payment={payment} />
            {reference ? (
                <p className="font-mono text-xs break-all text-muted-foreground">
                    {reference}
                </p>
            ) : null}
            <PaymentNotes payment={payment} />
            {payment.review_status === 'none' ? null : (
                <PaymentReviewInfo payment={payment} />
            )}
        </div>
    );
}

/**
 * Pembayaran untuk satu tagihan, termasuk pembayaran anomali yang perlu ditinjau
 * (tidak mengubah status tagihan).
 *
 * @param {{ payments: Payment[] }} props
 */
export default function InvoicePaymentList({ payments }) {
    return (
        <DataTable
            columns={columns}
            rows={payments}
            rowKey={(payment) => payment.id}
            mobileCard={PaymentCard}
            emptyState={<EmptyState title="Belum ada pembayaran" />}
        />
    );
}
