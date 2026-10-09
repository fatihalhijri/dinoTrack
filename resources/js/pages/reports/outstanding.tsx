import { Head, Link } from '@inertiajs/react';
import { Download, ReceiptText } from 'lucide-react';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/hooks/use-filters';
import { formatDate, formatNumber } from '@/lib/format';
import { show as customerShow } from '@/routes/customers';
import { show as invoiceShow } from '@/routes/invoices';
import { index as reportsIndex, outstanding } from '@/routes/reports';
import { outstanding as exportOutstanding } from '@/routes/reports/export';
import type { OutstandingInvoice, Paginated } from '@/types';

type OutstandingFilters = {
    search?: string;
    per_page?: string;
};

type ReportsOutstandingProps = {
    invoices: Paginated<OutstandingInvoice>;
    filters: OutstandingFilters;
};

const linkClass = 'font-medium text-primary underline-offset-4 hover:underline';

function CustomerCell({ invoice }: { invoice: OutstandingInvoice }) {
    return (
        <div className="min-w-0">
            <Link
                href={customerShow(invoice.customer_id)}
                className="font-medium hover:underline"
            >
                {invoice.customer_name}
            </Link>
            <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                <span className="font-mono">{invoice.customer_code}</span>
                <StatusBadge
                    kind="customer"
                    value={invoice.customer_status}
                    label={invoice.customer_status_label}
                />
            </span>
        </div>
    );
}

function InvoiceStatusBadge({ invoice }: { invoice: OutstandingInvoice }) {
    return (
        <StatusBadge
            kind="invoice"
            value={invoice.status}
            label={invoice.status_label}
        />
    );
}

function ageText(invoice: OutstandingInvoice): string {
    return `${formatNumber(invoice.age_days)} hari`;
}

const columns: DataTableColumn<OutstandingInvoice>[] = [
    {
        key: 'number',
        header: 'Tagihan',
        cell: (invoice) => (
            <Link href={invoiceShow(invoice.id)} className={linkClass}>
                {invoice.number}
            </Link>
        ),
    },
    {
        key: 'customer',
        header: 'Pelanggan',
        cell: (invoice) => <CustomerCell invoice={invoice} />,
    },
    {
        key: 'due_at',
        header: 'Jatuh tempo',
        className: 'whitespace-nowrap',
        cell: (invoice) => formatDate(invoice.due_at),
    },
    {
        key: 'age_days',
        header: 'Umur',
        align: 'right',
        className: 'whitespace-nowrap tabular-nums',
        cell: ageText,
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
        cell: (invoice) => <InvoiceStatusBadge invoice={invoice} />,
    },
];

function invoiceCard(invoice: OutstandingInvoice) {
    return (
        <div className="grid gap-2">
            <div className="flex items-start justify-between gap-3">
                <Link href={invoiceShow(invoice.id)} className={linkClass}>
                    {invoice.number}
                </Link>
                <Money amount={invoice.total} className="font-medium" />
            </div>
            <CustomerCell invoice={invoice} />
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-muted-foreground">
                    Jatuh tempo {formatDate(invoice.due_at)} ·{' '}
                    <span className="font-medium text-foreground">
                        {ageText(invoice)}
                    </span>
                </span>
                <InvoiceStatusBadge invoice={invoice} />
            </div>
        </div>
    );
}

export default function ReportsOutstanding({
    invoices,
    filters: initialFilters,
}: ReportsOutstandingProps) {
    const filters = useFilters<OutstandingFilters>(initialFilters);

    return (
        <>
            <Head title="Tunggakan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tunggakan"
                    description="Tagihan belum dibayar yang sudah lewat jatuh tempo, paling lama lebih dulu. Umur dihitung sejak jatuh tempo."
                    actions={
                        <Button asChild variant="outline" className="h-10">
                            <a href={exportOutstanding.url()} download>
                                <Download aria-hidden="true" />
                                Unduh CSV
                            </a>
                        </Button>
                    }
                />

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari nomor, kode, atau nama…"
                />

                <DataTable
                    columns={columns}
                    rows={invoices.data}
                    rowKey={(invoice) => invoice.id}
                    mobileCard={invoiceCard}
                    emptyState={
                        filters.hasActiveFilters ? (
                            <EmptyState
                                icon={ReceiptText}
                                title="Tidak ada tunggakan yang cocok"
                                description="Ubah kata kunci atau hapus filter."
                            />
                        ) : (
                            <EmptyState
                                icon={ReceiptText}
                                title="Tidak ada tunggakan"
                                description="Semua tagihan yang sudah lewat jatuh tempo telah dibayar atau dibatalkan."
                            />
                        )
                    }
                />

                <Pagination paginator={invoices} />
            </div>
        </>
    );
}

ReportsOutstanding.layout = {
    breadcrumbs: [
        {
            title: 'Laporan',
            href: reportsIndex(),
        },
        {
            title: 'Tunggakan',
            href: outstanding(),
        },
    ],
};
