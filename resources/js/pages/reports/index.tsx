import { Head, Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    BarChart3,
    ChevronLeft,
    ChevronRight,
    Download,
    ShieldOff,
    UserPlus,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import InputError from '@/components/input-error';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import PageSection from '@/components/page-section';
import RevenueChart from '@/components/reports/revenue-chart';
import StatCard from '@/components/stat-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    formatMonth,
    formatNumber,
    formatPeriod,
    toDateInputValue,
} from '@/lib/format';
import { index as reportsIndex, outstanding } from '@/routes/reports';
import {
    outstanding as exportOutstanding,
    payments as exportPayments,
    revenue as exportRevenue,
} from '@/routes/reports/export';
import type {
    AgingBucketTotal,
    CustomerMovement,
    MonthlyRevenue,
    PaymentMethod,
    SelectOption,
} from '@/types';

type ReportsIndexProps = {
    year: number;
    from: string;
    to: string;
    revenue: MonthlyRevenue[];
    aging: AgingBucketTotal[];
    movement: CustomerMovement;
    methods: SelectOption<PaymentMethod>[];
};

/** Sama dengan `ReportRequest::MIN_YEAR`. */
const MIN_YEAR = 2000;

type ReportQuery = { year: number; from: string; to: string };

/** Hanya prop yang berubah yang dimuat ulang; umur tunggakan tidak bergantung pada parameter. */
function visit(query: ReportQuery, only: (keyof ReportsIndexProps)[]): void {
    router.get(reportsIndex.url(), query, {
        only,
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const movementCards: {
    key: keyof Omit<CustomerMovement, 'from' | 'to'>;
    title: string;
    description: string;
    icon: LucideIcon;
}[] = [
    {
        key: 'new_customers',
        title: 'Pelanggan baru',
        description: 'Tanggal pasang di dalam periode',
        icon: UserPlus,
    },
    {
        key: 'terminated_customers',
        title: 'Berhenti',
        description: 'Diberhentikan di dalam periode',
        icon: UserX,
    },
    {
        key: 'isolated_customers',
        title: 'Terisolir',
        description: 'Diisolir dari status aktif',
        icon: ShieldOff,
    },
];

export default function ReportsIndex({
    year,
    from,
    to,
    revenue,
    aging,
    movement,
    methods,
}: ReportsIndexProps) {
    const { errors } = usePage().props;
    const [range, setRange] = useState({ from, to });
    const currentYear = Number(toDateInputValue().slice(0, 4));

    const yearTotal = revenue.reduce((sum, month) => sum + month.total, 0);
    const yearPayments = revenue.reduce(
        (sum, month) => sum + month.payment_count,
        0,
    );
    const outstandingTotal = aging.reduce(
        (sum, bucket) => sum + bucket.amount,
        0,
    );
    const outstandingInvoices = aging.reduce(
        (sum, bucket) => sum + bucket.invoice_count,
        0,
    );

    const changeYear = (next: number) =>
        visit({ year: next, from, to }, ['year', 'revenue']);

    const changeRange = (key: 'from' | 'to', value: string) => {
        const next = { ...range, [key]: value };

        setRange(next);

        if (value !== '') {
            visit({ year, ...next }, ['from', 'to', 'movement']);
        }
    };

    const revenueColumns: DataTableColumn<MonthlyRevenue>[] = [
        {
            key: 'month',
            header: 'Bulan',
            cell: (month) => formatMonth(month.month),
        },
        ...methods.map((method): DataTableColumn<MonthlyRevenue> => ({
            key: method.value,
            header: method.label,
            align: 'right',
            cell: (month) => <Money amount={month.by_method[method.value]} />,
        })),
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            cell: (month) => (
                <Money amount={month.total} className="font-medium" />
            ),
        },
        {
            key: 'payment_count',
            header: 'Pembayaran',
            align: 'right',
            className: 'tabular-nums',
            cell: (month) => formatNumber(month.payment_count),
        },
    ];

    const revenueCard = (month: MonthlyRevenue) => (
        <div className="grid gap-1.5">
            <div className="flex items-start justify-between gap-3 font-medium">
                <span>{formatMonth(month.month)}</span>
                <Money amount={month.total} />
            </div>
            {methods.map((method) => (
                <div
                    key={method.value}
                    className="flex items-center justify-between gap-3 text-muted-foreground"
                >
                    <span>{method.label}</span>
                    <Money amount={month.by_method[method.value]} />
                </div>
            ))}
            <p className="text-xs text-muted-foreground">
                {formatNumber(month.payment_count)} pembayaran
            </p>
        </div>
    );

    return (
        <>
            <Head title="Laporan" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <PageHeader
                    title="Laporan"
                    description="Pendapatan dihitung dari pembayaran normal menurut tanggal bayar; pembayaran anomali tidak termasuk."
                />

                <PageSection
                    title={`Pendapatan ${year}`}
                    aside={
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-10"
                                aria-label="Tahun sebelumnya"
                                disabled={year <= MIN_YEAR}
                                onClick={() => changeYear(year - 1)}
                            >
                                <ChevronLeft />
                            </Button>
                            <span className="w-12 text-center text-sm font-medium text-foreground tabular-nums">
                                {year}
                            </span>
                            <Button
                                variant="outline"
                                size="icon"
                                className="size-10"
                                aria-label="Tahun berikutnya"
                                disabled={year >= currentYear}
                                onClick={() => changeYear(year + 1)}
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    }
                >
                    <div className="rounded-xl border bg-card p-4 text-card-foreground shadow-sm">
                        <p className="text-sm text-muted-foreground">
                            Total setahun
                        </p>
                        <p className="text-2xl font-semibold tracking-tight">
                            <Money amount={yearTotal} />
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatNumber(yearPayments)} pembayaran
                        </p>
                        <div className="mt-4">
                            {yearTotal > 0 ? (
                                <RevenueChart
                                    revenue={revenue}
                                    year={year}
                                    methods={methods}
                                />
                            ) : (
                                <EmptyState
                                    icon={BarChart3}
                                    title={`Belum ada pendapatan di tahun ${year}`}
                                    description="Pendapatan muncul setelah ada pembayaran yang tercatat."
                                />
                            )}
                        </div>
                    </div>

                    {yearTotal > 0 ? (
                        <DataTable
                            columns={revenueColumns}
                            rows={revenue}
                            rowKey={(month) => month.month}
                            mobileCard={revenueCard}
                        />
                    ) : null}
                </PageSection>

                <PageSection
                    title="Umur tunggakan"
                    aside={
                        <>
                            <Money amount={outstandingTotal} /> ·{' '}
                            {formatNumber(outstandingInvoices)} tagihan lewat
                            jatuh tempo
                        </>
                    }
                >
                    <div className="grid gap-3 sm:grid-cols-3 sm:gap-4">
                        {aging.map((bucket) => (
                            <StatCard
                                key={bucket.bucket}
                                title={bucket.label}
                                href={outstanding()}
                                value={<Money amount={bucket.amount} />}
                                description={`${formatNumber(bucket.invoice_count)} tagihan`}
                            />
                        ))}
                    </div>
                    <Link
                        href={outstanding()}
                        prefetch
                        className="inline-flex min-h-10 items-center gap-1 self-start text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Lihat daftar tunggakan
                        <ChevronRight className="size-4" aria-hidden="true" />
                    </Link>
                </PageSection>

                <PageSection
                    title="Pergerakan pelanggan"
                    aside={formatPeriod(movement.from, movement.to)}
                >
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:items-end sm:gap-3">
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Dari</span>
                            <Input
                                type="date"
                                value={range.from}
                                max={range.to}
                                onChange={(event) =>
                                    changeRange('from', event.target.value)
                                }
                                className="h-10 w-full sm:w-44"
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">
                                Sampai
                            </span>
                            <Input
                                type="date"
                                value={range.to}
                                min={range.from}
                                onChange={(event) =>
                                    changeRange('to', event.target.value)
                                }
                                className="h-10 w-full sm:w-44"
                            />
                        </label>
                    </div>
                    <InputError message={errors.from ?? errors.to} />

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                        {movementCards.map((card) => (
                            <StatCard
                                key={card.key}
                                title={card.title}
                                icon={card.icon}
                                value={formatNumber(movement[card.key])}
                                description={card.description}
                            />
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Dihitung dari riwayat aktivitas pelanggan; data yang
                        dibuat tanpa aplikasi (impor atau data demo) tidak
                        terhitung.
                    </p>
                </PageSection>

                <PageSection title="Unduh CSV">
                    <div className="grid gap-2 sm:flex sm:flex-wrap">
                        <DownloadLink
                            href={exportPayments.url({
                                query: { from: movement.from, to: movement.to },
                            })}
                            label={`Pembayaran ${formatPeriod(movement.from, movement.to)}`}
                        />
                        <DownloadLink
                            href={exportOutstanding.url()}
                            label="Tunggakan saat ini"
                        />
                        <DownloadLink
                            href={exportRevenue.url({ query: { year } })}
                            label={`Pendapatan ${year}`}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Format Excel Indonesia (pemisah titik koma). Rincian
                        pembayaran memakai periode pergerakan pelanggan di atas
                        dan ikut memuat pembayaran anomali.
                    </p>
                </PageSection>
            </div>
        </>
    );
}

/** Unduhan file biasa, bukan kunjungan Inertia. */
function DownloadLink({ href, label }: { href: string; label: string }) {
    return (
        <Button asChild variant="outline" className="h-10 justify-start">
            <a href={href} download>
                <Download aria-hidden="true" />
                {label}
            </a>
        </Button>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Laporan',
            href: reportsIndex(),
        },
    ],
};
