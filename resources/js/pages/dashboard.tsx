import type { InertiaLinkProps } from '@inertiajs/react';
import { Head, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    CalendarClock,
    ChevronRight,
    CircleAlert,
    Clock,
    LayoutDashboard,
    ReceiptText,
    Router as RouterIcon,
    ShieldOff,
    UserCheck,
    UserX,
} from 'lucide-react';
import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import Money from '@/components/money';
import PageHeader from '@/components/page-header';
import StatCard from '@/components/stat-card';
import { useCan } from '@/hooks/use-can';
import { formatDateTime, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as customersIndex } from '@/routes/customers';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as paymentsIndex } from '@/routes/payments';
import { index as reportsIndex, outstanding } from '@/routes/reports';
import type { CustomerCounts, CustomerStatus, DashboardSummary } from '@/types';

type DashboardProps = {
    summary: DashboardSummary | null;
    customer_counts: CustomerCounts | null;
};

const customerCards: {
    status: CustomerStatus;
    title: string;
    icon: LucideIcon;
}[] = [
    { status: 'active', title: 'Pelanggan aktif', icon: UserCheck },
    { status: 'isolated', title: 'Diisolir', icon: ShieldOff },
    { status: 'pending', title: 'Menunggu pemasangan', icon: Clock },
    { status: 'terminated', title: 'Berhenti', icon: UserX },
];

export default function Dashboard({
    summary,
    customer_counts: customerCounts,
}: DashboardProps) {
    const can = useCan();

    const reviewCount = summary?.payments_needing_review ?? 0;
    const networkErrorCount =
        customerCounts?.network_error ??
        summary?.customers_with_network_error ??
        0;
    const showReview = can('payments.view') && reviewCount > 0;
    const showNetworkError = can('customers.view') && networkErrorCount > 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={
                        summary
                            ? 'Ringkasan keuangan dan pelanggan.'
                            : 'Ringkasan pelanggan.'
                    }
                />

                {showReview || showNetworkError ? (
                    <Section title="Perlu perhatian">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {showReview && summary ? (
                                <AttentionLink
                                    href={paymentsIndex({
                                        query: {
                                            review_status: 'needs_review',
                                        },
                                    })}
                                    icon={CircleAlert}
                                    title={`${formatNumber(reviewCount)} pembayaran perlu tinjauan`}
                                    description={
                                        <>
                                            Total{' '}
                                            <Money
                                                amount={
                                                    summary.payments_needing_review_amount
                                                }
                                            />
                                        </>
                                    }
                                />
                            ) : null}
                            {showNetworkError ? (
                                <AttentionLink
                                    href={customersIndex({
                                        query: { network_error: true },
                                    })}
                                    icon={RouterIcon}
                                    title={`${formatNumber(networkErrorCount)} pelanggan dengan galat router`}
                                    description="Perintah ke router gagal setelah semua percobaan."
                                />
                            ) : null}
                        </div>
                    </Section>
                ) : null}

                {summary ? (
                    <Section
                        title="Keuangan"
                        aside={`Diperbarui ${formatDateTime(summary.generated_at)} · tiap 5 menit`}
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <StatCard
                                title="Pendapatan bulan ini"
                                icon={Banknote}
                                href={reportsIndex()}
                                value={
                                    <Money
                                        amount={summary.revenue_this_month}
                                    />
                                }
                                description={`${formatNumber(summary.payments_this_month)} pembayaran`}
                            />
                            <StatCard
                                title="Tunggakan"
                                icon={ReceiptText}
                                href={outstanding()}
                                value={
                                    <Money
                                        amount={summary.outstanding_amount}
                                    />
                                }
                                description={`${formatNumber(summary.outstanding_invoices)} tagihan lewat jatuh tempo`}
                            />
                            <StatCard
                                title="Jatuh tempo 7 hari ke depan"
                                icon={CalendarClock}
                                href={invoicesIndex({
                                    query: { due: 'this_week' },
                                })}
                                value={
                                    <Money
                                        amount={summary.due_this_week_amount}
                                    />
                                }
                                description={`${formatNumber(summary.due_this_week_invoices)} tagihan belum dibayar`}
                            />
                        </div>
                    </Section>
                ) : null}

                {customerCounts ? (
                    <Section title="Pelanggan">
                        <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                            {customerCards.map((card) => (
                                <StatCard
                                    key={card.status}
                                    title={card.title}
                                    icon={card.icon}
                                    href={customersIndex({
                                        query: { status: card.status },
                                    })}
                                    value={formatNumber(
                                        customerCounts[card.status],
                                    )}
                                />
                            ))}
                        </div>
                    </Section>
                ) : null}

                {!summary && !customerCounts ? (
                    <EmptyState
                        icon={LayoutDashboard}
                        title="Belum ada ringkasan untuk akun ini"
                        description="Hubungi admin jika Anda memerlukan akses ke data pelanggan atau laporan."
                    />
                ) : null}
            </div>
        </>
    );
}

function Section({
    title,
    aside,
    children,
}: {
    title: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <h2 className="text-base font-semibold">{title}</h2>
                {aside ? (
                    <p className="text-xs text-muted-foreground">{aside}</p>
                ) : null}
            </div>
            {children}
        </section>
    );
}

function AttentionLink({
    href,
    icon: Icon,
    title,
    description,
}: {
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    title: string;
    description: ReactNode;
}) {
    return (
        <Link
            href={href}
            prefetch
            className="flex min-h-10 items-center gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm transition-colors hover:bg-warning/15 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Icon className="size-5 shrink-0 text-warning" aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <p className="font-medium">{title}</p>
                <p className="text-muted-foreground">{description}</p>
            </div>
            <ChevronRight
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
        </Link>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
