import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import Money from '@/components/money';
import StatusBadge from '@/components/status-badge';
import { formatDateTime } from '@/lib/format';
import type { Payment } from '@/types';

/** Referensi yang bisa ditelusuri: order_id QRIS atau ID transaksi gateway. */
function paymentReference(payment: Payment): string | null {
    return payment.order_id ?? payment.reference;
}

function MethodText({ payment }: { payment: Payment }) {
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

function ReviewInfo({ payment }: { payment: Payment }) {
    if (payment.review_status === 'none') {
        return '—';
    }

    return (
        <div className="flex flex-col items-start gap-1">
            <StatusBadge
                kind="review"
                value={payment.review_status}
                label={payment.review_status_label}
            />
            {payment.review_note ? (
                <p className="text-xs whitespace-pre-line text-muted-foreground">
                    {payment.review_note}
                </p>
            ) : null}
        </div>
    );
}

function PaymentNotes({ payment }: { payment: Payment }) {
    return payment.notes ? (
        <p className="text-xs whitespace-pre-line text-muted-foreground">
            {payment.notes}
        </p>
    ) : null;
}

const columns: DataTableColumn<Payment>[] = [
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
        cell: (payment) => <ReviewInfo payment={payment} />,
    },
];

function PaymentCard(payment: Payment) {
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
                <ReviewInfo payment={payment} />
            )}
        </div>
    );
}

/**
 * Pembayaran untuk satu tagihan, termasuk pembayaran anomali yang perlu ditinjau
 * (tidak mengubah status tagihan).
 */
export default function InvoicePaymentList({
    payments,
}: {
    payments: Payment[];
}) {
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
