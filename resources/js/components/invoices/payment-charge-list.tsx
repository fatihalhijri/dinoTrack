import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import Money from '@/components/money';
import StatusBadge from '@/components/status-badge';
import { formatDateTime } from '@/lib/format';
import type { PaymentCharge } from '@/types';

function ChargeStatusBadge({ charge }: { charge: PaymentCharge }) {
    return (
        <StatusBadge
            kind="charge"
            value={charge.status}
            label={charge.status_label}
        />
    );
}

const columns: DataTableColumn<PaymentCharge>[] = [
    {
        key: 'attempt',
        header: 'Percobaan',
        cell: (charge) => (
            <span className="tabular-nums">#{charge.attempt}</span>
        ),
    },
    {
        key: 'order_id',
        header: 'Order ID',
        cell: (charge) => (
            <span className="font-mono text-xs break-all">
                {charge.order_id}
            </span>
        ),
    },
    {
        key: 'amount',
        header: 'Nominal',
        align: 'right',
        cell: (charge) => <Money amount={charge.amount} />,
    },
    {
        key: 'status',
        header: 'Status',
        cell: (charge) => <ChargeStatusBadge charge={charge} />,
    },
    {
        key: 'created_at',
        header: 'Dibuat',
        cell: (charge) => formatDateTime(charge.created_at),
    },
    {
        key: 'expires_at',
        header: 'Kedaluwarsa',
        cell: (charge) => formatDateTime(charge.expires_at),
    },
];

function ChargeCard(charge: PaymentCharge) {
    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="font-medium tabular-nums">
                        Percobaan #{charge.attempt}
                    </p>
                    <p className="font-mono text-xs break-all text-muted-foreground">
                        {charge.order_id}
                    </p>
                </div>
                <ChargeStatusBadge charge={charge} />
            </div>
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">
                    Kedaluwarsa {formatDateTime(charge.expires_at)}
                </span>
                <Money amount={charge.amount} className="font-medium" />
            </div>
        </div>
    );
}

/** Riwayat permintaan QRIS ke gateway untuk satu tagihan, percobaan terbaru dulu. */
export default function PaymentChargeList({
    charges,
}: {
    charges: PaymentCharge[];
}) {
    return (
        <DataTable
            columns={columns}
            rows={charges}
            rowKey={(charge) => charge.id}
            mobileCard={ChargeCard}
            emptyState={
                <EmptyState
                    title="Belum ada QRIS"
                    description="QRIS dibuat saat pelanggan menekan tombol bayar di halaman link bayar."
                />
            }
        />
    );
}
