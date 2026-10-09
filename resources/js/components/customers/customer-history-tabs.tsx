import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import MessageLogList from '@/components/message-log-list';
import Money from '@/components/money';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatDateTime, formatPeriod } from '@/lib/format';
import { index as invoicesIndex, show as invoiceShow } from '@/routes/invoices';
import { index as paymentsIndex } from '@/routes/payments';
import type { ActivityLog, Invoice, MessageLog, Payment } from '@/types';

const linkClass = 'font-medium text-primary underline-offset-4 hover:underline';

const invoiceColumns: DataTableColumn<Invoice>[] = [
    {
        key: 'number',
        header: 'Nomor',
        cell: (invoice) => (
            <Link href={invoiceShow(invoice.id)} className={linkClass}>
                {invoice.number}
            </Link>
        ),
    },
    {
        key: 'period',
        header: 'Periode',
        cell: (invoice) =>
            formatPeriod(invoice.period_start, invoice.period_end),
    },
    {
        key: 'due_at',
        header: 'Jatuh tempo',
        cell: (invoice) => formatDate(invoice.due_at),
    },
    {
        key: 'total',
        header: 'Total',
        align: 'right',
        cell: (invoice) => <Money amount={invoice.total} />,
    },
    {
        key: 'status',
        header: 'Status',
        cell: (invoice) => (
            <StatusBadge
                kind="invoice"
                value={invoice.status}
                label={invoice.status_label}
            />
        ),
    },
];

const paymentColumns: DataTableColumn<Payment>[] = [
    {
        key: 'paid_at',
        header: 'Tanggal bayar',
        cell: (payment) => formatDateTime(payment.paid_at),
    },
    {
        key: 'invoice',
        header: 'Tagihan',
        cell: (payment) =>
            payment.invoice ? (
                <Link
                    href={invoiceShow(payment.invoice.id)}
                    className={linkClass}
                >
                    {payment.invoice.number}
                </Link>
            ) : (
                '—'
            ),
    },
    {
        key: 'method',
        header: 'Metode',
        cell: (payment) => (
            <span>
                {payment.method_label}
                {payment.received_by ? (
                    <span className="block text-xs text-muted-foreground">
                        oleh {payment.received_by.name}
                    </span>
                ) : null}
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
        cell: (payment) =>
            payment.review_status === 'none' ? (
                '—'
            ) : (
                <StatusBadge
                    kind="review"
                    value={payment.review_status}
                    label={payment.review_status_label}
                />
            ),
    },
];

function InvoiceCard(invoice: Invoice) {
    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-2">
                <Link href={invoiceShow(invoice.id)} className={linkClass}>
                    {invoice.number}
                </Link>
                <StatusBadge
                    kind="invoice"
                    value={invoice.status}
                    label={invoice.status_label}
                />
            </div>
            <p className="text-muted-foreground">
                {formatPeriod(invoice.period_start, invoice.period_end)}
            </p>
            <div className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">
                    Jatuh tempo {formatDate(invoice.due_at)}
                </span>
                <Money amount={invoice.total} className="font-medium" />
            </div>
        </div>
    );
}

function PaymentCard(payment: Payment) {
    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-2">
                <span className="font-medium">
                    {formatDateTime(payment.paid_at)}
                </span>
                <Money amount={payment.amount} className="font-medium" />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-muted-foreground">
                    {payment.method_label}
                    {payment.received_by
                        ? ` · oleh ${payment.received_by.name}`
                        : ''}
                </span>
                {payment.review_status === 'none' ? null : (
                    <StatusBadge
                        kind="review"
                        value={payment.review_status}
                        label={payment.review_status_label}
                    />
                )}
            </div>
            {payment.invoice ? (
                <Link
                    href={invoiceShow(payment.invoice.id)}
                    className={linkClass}
                >
                    {payment.invoice.number}
                </Link>
            ) : null}
        </div>
    );
}

/** Keterangan dari properti log yang aman dan berguna ditampilkan (alasan, galat router). */
function activityNote(activity: ActivityLog): string | null {
    const note = activity.properties?.reason ?? activity.properties?.error;

    return typeof note === 'string' && note !== '' ? note : null;
}

function ActivityList({ activities }: { activities: ActivityLog[] }) {
    if (activities.length === 0) {
        return (
            <div className="rounded-xl border bg-card">
                <EmptyState title="Belum ada aktivitas tercatat" />
            </div>
        );
    }

    return (
        <ol className="flex flex-col divide-y rounded-xl border bg-card">
            {activities.map((activity) => {
                const note = activityNote(activity);

                return (
                    <li key={activity.id} className="grid gap-1 p-4 text-sm">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <span className="font-medium">
                                {activity.action_label}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {formatDateTime(activity.created_at)}
                            </span>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {activity.user?.name ?? 'Sistem'}
                        </span>
                        {note ? (
                            <p className="whitespace-pre-line">{note}</p>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

/**
 * Riwayat pelanggan (24 terbaru per jenis). Tab yang prop-nya `null` (tanpa permission)
 * tidak ditampilkan; tab pertama yang tersedia menjadi tab awal.
 */
export default function CustomerHistoryTabs({
    customerId,
    invoices,
    payments,
    messages,
    activities,
}: {
    customerId: number;
    invoices: Invoice[] | null;
    payments: Payment[] | null;
    messages: MessageLog[] | null;
    activities: ActivityLog[];
}) {
    const tabs = [
        invoices !== null ? { value: 'invoices', label: 'Tagihan' } : null,
        payments !== null ? { value: 'payments', label: 'Pembayaran' } : null,
        messages !== null ? { value: 'messages', label: 'Pesan WA' } : null,
        { value: 'activities', label: 'Aktivitas' },
    ].filter((tab) => tab !== null);

    return (
        <Tabs defaultValue={tabs[0].value} className="gap-4">
            <div className="-mx-1 overflow-x-auto overflow-y-hidden px-1">
                <TabsList className="w-full min-w-max group-data-[orientation=horizontal]/tabs:h-10">
                    {tabs.map((tab) => (
                        <TabsTrigger key={tab.value} value={tab.value}>
                            {tab.label}
                        </TabsTrigger>
                    ))}
                </TabsList>
            </div>

            {invoices !== null ? (
                <TabsContent value="invoices" className="flex flex-col gap-3">
                    <DataTable
                        columns={invoiceColumns}
                        rows={invoices}
                        rowKey={(invoice) => invoice.id}
                        mobileCard={InvoiceCard}
                        emptyState={<EmptyState title="Belum ada tagihan" />}
                    />
                    {invoices.length > 0 ? (
                        <Button
                            asChild
                            variant="ghost"
                            className="h-10 self-end"
                        >
                            <Link
                                href={invoicesIndex({
                                    query: { customer_id: customerId },
                                })}
                            >
                                Lihat semua tagihan
                                <ArrowRight />
                            </Link>
                        </Button>
                    ) : null}
                </TabsContent>
            ) : null}

            {payments !== null ? (
                <TabsContent value="payments" className="flex flex-col gap-3">
                    <DataTable
                        columns={paymentColumns}
                        rows={payments}
                        rowKey={(payment) => payment.id}
                        mobileCard={PaymentCard}
                        emptyState={<EmptyState title="Belum ada pembayaran" />}
                    />
                    {payments.length > 0 ? (
                        <Button
                            asChild
                            variant="ghost"
                            className="h-10 self-end"
                        >
                            <Link
                                href={paymentsIndex({
                                    query: { customer_id: customerId },
                                })}
                            >
                                Lihat semua pembayaran
                                <ArrowRight />
                            </Link>
                        </Button>
                    ) : null}
                </TabsContent>
            ) : null}

            {messages !== null ? (
                <TabsContent value="messages">
                    <MessageLogList messages={messages} />
                </TabsContent>
            ) : null}

            <TabsContent value="activities">
                <ActivityList activities={activities} />
            </TabsContent>
        </Tabs>
    );
}
