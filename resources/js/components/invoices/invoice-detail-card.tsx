import { Link } from '@inertiajs/react';
import DetailRow from '@/components/detail-row';
import Money from '@/components/money';
import StatusBadge from '@/components/status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    formatDate,
    formatDateTime,
    formatNumber,
    formatPeriod,
    formatPhone,
} from '@/lib/format';
import { show as customerShow } from '@/routes/customers';
import type { BusinessIdentity, Invoice } from '@/types';

function TotalRow({
    label,
    amount,
    strong = false,
}: {
    label: string;
    amount: number;
    strong?: boolean;
}) {
    return (
        <div
            className={
                strong
                    ? 'flex items-center justify-between gap-4 text-base font-semibold'
                    : 'flex items-center justify-between gap-4 text-sm text-muted-foreground'
            }
        >
            <span>{label}</span>
            <Money amount={amount} />
        </div>
    );
}

/**
 * Rincian tagihan: pelanggan, tanggal, item, dan total. Satu-satunya bagian halaman yang
 * ikut tercetak; identitas usaha hanya tampil di hasil cetak.
 */
export default function InvoiceDetailCard({
    invoice,
    business,
}: {
    invoice: Invoice;
    business: BusinessIdentity;
}) {
    const items = invoice.items ?? [];

    return (
        <Card className="print:gap-4 print:border-0 print:py-0 print:shadow-none">
            <div className="hidden px-6 print:block print:px-0">
                <p className="text-lg font-semibold">{business.name}</p>
                {business.address ? (
                    <p className="text-sm whitespace-pre-line">
                        {business.address}
                    </p>
                ) : null}
                {business.whatsapp ? (
                    <p className="text-sm">
                        WhatsApp {formatPhone(business.whatsapp)}
                    </p>
                ) : null}
                <Separator className="mt-4" />
            </div>

            <CardHeader className="print:px-0">
                <CardTitle className="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <span className="hidden print:inline">Tagihan </span>
                        {invoice.number}
                    </span>
                    <StatusBadge
                        kind="invoice"
                        value={invoice.status}
                        label={invoice.status_label}
                    />
                </CardTitle>
            </CardHeader>

            <CardContent className="flex flex-col gap-6 print:px-0">
                <dl className="grid gap-4 sm:grid-cols-2 print:grid-cols-2">
                    <DetailRow label="Pelanggan">
                        {invoice.customer ? (
                            <>
                                <Link
                                    href={customerShow(invoice.customer.id)}
                                    className="font-medium text-primary underline-offset-4 hover:underline print:text-foreground"
                                >
                                    {invoice.customer.name}
                                </Link>
                                <span className="block font-mono text-xs text-muted-foreground">
                                    {invoice.customer.code}
                                </span>
                            </>
                        ) : (
                            '—'
                        )}
                    </DetailRow>
                    <DetailRow label="Periode">
                        {formatPeriod(invoice.period_start, invoice.period_end)}
                    </DetailRow>
                    <DetailRow label="Tanggal terbit">
                        {formatDate(invoice.issued_at)}
                    </DetailRow>
                    <DetailRow label="Jatuh tempo">
                        {formatDate(invoice.due_at)}
                    </DetailRow>
                    {invoice.paid_at ? (
                        <DetailRow label="Dibayar">
                            {formatDateTime(invoice.paid_at)}
                        </DetailRow>
                    ) : null}
                    {invoice.cancelled_at ? (
                        <DetailRow label="Dibatalkan">
                            {formatDateTime(invoice.cancelled_at)}
                        </DetailRow>
                    ) : null}
                </dl>

                <div className="flex flex-col gap-3">
                    <h3 className="text-sm font-medium">Rincian</h3>
                    <ul className="flex flex-col divide-y rounded-lg border">
                        {items.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-start justify-between gap-4 p-3 text-sm"
                            >
                                <div className="min-w-0">
                                    <p className="break-words">
                                        {item.description}
                                    </p>
                                    <p className="text-xs text-muted-foreground tabular-nums">
                                        {formatNumber(item.quantity)} ×{' '}
                                        <Money amount={item.unit_price} />
                                    </p>
                                </div>
                                <Money
                                    amount={item.amount}
                                    className="font-medium"
                                />
                            </li>
                        ))}
                    </ul>

                    <div className="flex flex-col gap-1.5 sm:ml-auto sm:w-72 print:ml-auto print:w-72">
                        <TotalRow label="Subtotal" amount={invoice.subtotal} />
                        {invoice.discount > 0 ? (
                            <TotalRow
                                label="Diskon (dikurangkan)"
                                amount={invoice.discount}
                            />
                        ) : null}
                        {invoice.penalty > 0 ? (
                            <TotalRow label="Denda" amount={invoice.penalty} />
                        ) : null}
                        <Separator className="my-1" />
                        <TotalRow label="Total" amount={invoice.total} strong />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
