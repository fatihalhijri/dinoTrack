import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Ban, TriangleAlert } from 'lucide-react';

import InvoiceActions from '@/components/invoices/invoice-actions';
import InvoiceDetailCard from '@/components/invoices/invoice-detail-card';
import InvoicePaymentList from '@/components/invoices/invoice-payment-list';
import PaymentChargeList from '@/components/invoices/payment-charge-list';
import PaymentLinkCard from '@/components/invoices/payment-link-card';
import MessageLogList from '@/components/message-log-list';
import StatusBadge from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useCan } from '@/hooks/use-can';
import { formatDateTime, formatNumber } from '@/lib/format';
import { show as customerShow } from '@/routes/customers';
import { index as invoicesIndex, show } from '@/routes/invoices';
import { index as paymentsIndex } from '@/routes/payments';
/** @import { ReactNode } from 'react' */
/**
 * @import {
 *     BusinessIdentity,
 *     Invoice,
 *     InvoiceReference,
 *     MessageLog,
 *     PackageOption,
 *     PaymentMethod,
 *     SelectOption,
 * } from '@/types'
 */

/**
 * @typedef {object} InvoicesShowProps
 * @property {Invoice} invoice
 * @property {string} payment_link
 * @property {MessageLog[]} messages
 * @property {PackageOption[] | null} packages Paket koreksi untuk terbit ulang; `null` tanpa `invoices.cancel`.
 * @property {InvoiceReference | null} replacement Tagihan aktif untuk periode yang sama (hasil terbit ulang), hanya untuk tagihan batal.
 * @property {BusinessIdentity} business
 * @property {SelectOption<PaymentMethod>[] | null} payment_methods Metode pembayaran manual (tunai/transfer); `null` tanpa `payments.record`.
 */

/** @param {{ title: string; children: ReactNode }} props */
function Section({ title, children }) {
    return (
        <section className="flex min-w-0 flex-col gap-3 print:hidden">
            <h2 className="text-base font-semibold">{title}</h2>
            {children}
        </section>
    );
}

/** @param {InvoicesShowProps} props */
export default function InvoicesShow({
    invoice,
    payment_link: paymentLink,
    messages,
    packages,
    replacement,
    business,
    payment_methods: paymentMethods,
}) {
    const can = useCan();
    const isOutstanding =
        invoice.status === 'unpaid' || invoice.status === 'overdue';
    const needsReviewCount = (invoice.payments ?? []).filter(
        (payment) => payment.review_status === 'needs_review',
    ).length;

    return (
        <>
            <Head title={invoice.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 print:p-0">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between print:hidden">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight break-words">
                            {invoice.number}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <StatusBadge
                                kind="invoice"
                                value={invoice.status}
                                label={invoice.status_label}
                            />
                            {invoice.customer ? (
                                <Link
                                    href={customerShow(invoice.customer.id)}
                                    className="hover:underline"
                                >
                                    <span className="font-mono">
                                        {invoice.customer.code}
                                    </span>{' '}
                                    · {invoice.customer.name}
                                </Link>
                            ) : null}
                        </div>
                    </div>
                    <InvoiceActions
                        invoice={invoice}
                        packages={packages}
                        replacement={replacement}
                        paymentMethods={paymentMethods}
                    />
                </div>

                {invoice.status === 'cancelled' ? (
                    <Alert className="print:hidden">
                        <Ban />
                        <AlertTitle>
                            Dibatalkan {formatDateTime(invoice.cancelled_at)}
                        </AlertTitle>
                        <AlertDescription>
                            <p className="whitespace-pre-line">
                                Alasan: {invoice.cancelled_reason ?? '—'}
                            </p>
                            {replacement ? (
                                <Link
                                    href={show(replacement.id)}
                                    className="inline-flex min-h-10 items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Sudah diterbitkan ulang sebagai{' '}
                                    {replacement.number}
                                    <ArrowRight className="size-4" />
                                </Link>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {needsReviewCount > 0 ? (
                    <Alert className="border-warning/40 bg-warning/5 print:hidden [&>svg]:text-warning">
                        <TriangleAlert />
                        <AlertTitle>
                            {formatNumber(needsReviewCount)} pembayaran perlu
                            tinjauan
                        </AlertTitle>
                        <AlertDescription>
                            <p>
                                Pembayaran anomali tidak mengubah status
                                tagihan. Admin meninjaunya dan mengembalikan
                                dana secara manual bila perlu.
                            </p>
                            {can('payments.view') ? (
                                <Link
                                    href={paymentsIndex({
                                        query: {
                                            review_status: 'needs_review',
                                            search: invoice.number,
                                        },
                                    })}
                                    className="inline-flex min-h-10 items-center gap-1 font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    Buka di daftar pembayaran
                                    <ArrowRight className="size-4" />
                                </Link>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-3 print:block">
                    <div className="flex min-w-0 flex-col gap-6 lg:col-span-2">
                        {isOutstanding ? (
                            <PaymentLinkCard link={paymentLink} />
                        ) : null}

                        <InvoiceDetailCard
                            invoice={invoice}
                            business={business}
                        />

                        <Section title="Pembayaran">
                            <InvoicePaymentList
                                payments={invoice.payments ?? []}
                            />
                        </Section>

                        <Section title="Riwayat QRIS">
                            <PaymentChargeList
                                charges={invoice.payment_charges ?? []}
                            />
                        </Section>
                    </div>

                    <Section title="Pesan WhatsApp">
                        <MessageLogList messages={messages} />
                    </Section>
                </div>
            </div>
        </>
    );
}

InvoicesShow.layout = (/** @type {InvoicesShowProps} */ { invoice }) => ({
    breadcrumbs: [
        {
            title: 'Tagihan',
            href: invoicesIndex(),
        },
        {
            title: invoice.number,
            href: show(invoice.id),
        },
    ],
});
