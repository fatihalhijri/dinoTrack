import { Head, Link } from '@inertiajs/react';
import { CalendarClock, ReceiptText } from 'lucide-react';
import CustomerFilterChip from '@/components/customer-filter-chip';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { useFilters } from '@/hooks/use-filters';
import { formatDate, formatPeriod } from '@/lib/format';
import { show as customerShow } from '@/routes/customers';
import { index as invoicesIndex, show } from '@/routes/invoices';
import type {
    CustomerReference,
    Invoice,
    InvoiceStatus,
    Paginated,
    SelectOption,
} from '@/types';

type InvoiceFilters = {
    search?: string;
    status?: string;
    period?: string;
    customer_id?: string;
    due?: string;
    per_page?: string;
};

type InvoicesIndexProps = {
    invoices: Paginated<Invoice>;
    filters: InvoiceFilters;
    statuses: SelectOption<InvoiceStatus>[];
    customer: CustomerReference | null;
};

const ALL = 'all';

/** Nilai `due` untuk "jatuh tempo minggu ini" (hari ini sampai H+6, dihitung backend). */
const DUE_THIS_WEEK = 'this_week';

const linkClass = 'font-medium text-primary underline-offset-4 hover:underline';

function InvoiceStatusBadge({ invoice }: { invoice: Invoice }) {
    return (
        <StatusBadge
            kind="invoice"
            value={invoice.status}
            label={invoice.status_label}
        />
    );
}

const columns: DataTableColumn<Invoice>[] = [
    {
        key: 'number',
        header: 'Nomor',
        cell: (invoice) => (
            <Link href={show(invoice.id)} className={linkClass}>
                {invoice.number}
            </Link>
        ),
    },
    {
        key: 'customer',
        header: 'Pelanggan',
        cell: (invoice) =>
            invoice.customer ? (
                <div className="min-w-0">
                    <Link
                        href={customerShow(invoice.customer.id)}
                        className="font-medium hover:underline"
                    >
                        {invoice.customer.name}
                    </Link>
                    <span className="block font-mono text-xs text-muted-foreground">
                        {invoice.customer.code}
                    </span>
                </div>
            ) : (
                '—'
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
        cell: (invoice) => <InvoiceStatusBadge invoice={invoice} />,
    },
];

function InvoiceCard(invoice: Invoice) {
    return (
        <Link
            href={show(invoice.id)}
            className="-m-4 flex flex-col gap-2 rounded-xl p-4"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-medium break-words text-primary">
                        {invoice.number}
                    </p>
                    {invoice.customer ? (
                        <p className="break-words text-muted-foreground">
                            {invoice.customer.name}{' '}
                            <span className="font-mono text-xs">
                                · {invoice.customer.code}
                            </span>
                        </p>
                    ) : null}
                </div>
                <InvoiceStatusBadge invoice={invoice} />
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
        </Link>
    );
}

export default function InvoicesIndex({
    invoices,
    filters: initialFilters,
    statuses,
    customer,
}: InvoicesIndexProps) {
    const filters = useFilters<InvoiceFilters>(initialFilters);
    const customerId = filters.filters.customer_id;

    return (
        <>
            <Head title="Tagihan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tagihan"
                    description="Tagihan bulanan pelanggan, terbit otomatis setiap tanggal tagih."
                />

                {customerId ? (
                    <CustomerFilterChip
                        prefix="Tagihan milik"
                        customer={customer}
                        onClear={() =>
                            filters.setFilter('customer_id', undefined)
                        }
                    />
                ) : null}

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari nomor, kode, atau nama…"
                >
                    <div className="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:flex-wrap">
                        <Select
                            value={filters.filters.status ?? ALL}
                            onValueChange={(value) =>
                                filters.setFilter(
                                    'status',
                                    value === ALL ? undefined : value,
                                )
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-40"
                                aria-label="Status tagihan"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua status
                                </SelectItem>
                                {statuses.map((status) => (
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
                            type="month"
                            value={filters.filters.period ?? ''}
                            onChange={(event) =>
                                filters.setFilter(
                                    'period',
                                    event.target.value === ''
                                        ? undefined
                                        : event.target.value,
                                )
                            }
                            aria-label="Periode (bulan mulai)"
                            title="Periode (bulan mulai)"
                            className="h-10 w-full md:w-44"
                        />

                        <Toggle
                            variant="outline"
                            size="lg"
                            className="col-span-2 h-10 w-full md:w-auto"
                            pressed={filters.filters.due === DUE_THIS_WEEK}
                            onPressedChange={(pressed) =>
                                filters.setFilter(
                                    'due',
                                    pressed ? DUE_THIS_WEEK : undefined,
                                )
                            }
                        >
                            <CalendarClock />
                            Jatuh tempo 7 hari
                        </Toggle>
                    </div>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={invoices.data}
                    rowKey={(invoice) => invoice.id}
                    mobileCard={InvoiceCard}
                    emptyState={
                        filters.hasActiveFilters ? (
                            <EmptyState
                                icon={ReceiptText}
                                title="Tidak ada tagihan yang cocok"
                                description="Ubah kata kunci atau hapus filter."
                            />
                        ) : (
                            <EmptyState
                                icon={ReceiptText}
                                title="Belum ada tagihan"
                                description="Tagihan pertama terbit saat pelanggan ditandai terpasang, lalu setiap tanggal tagihnya."
                            />
                        )
                    }
                />

                <Pagination paginator={invoices} />
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tagihan',
            href: invoicesIndex(),
        },
    ],
};
