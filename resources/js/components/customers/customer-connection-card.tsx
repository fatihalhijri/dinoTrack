import { Deferred } from '@inertiajs/react';
import { DetailRow } from '@/components/customers/customer-profile-card';
import Money from '@/components/money';
import type { StatusTone } from '@/components/status-badge';
import { toneClasses } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Customer } from '@/types';

/** Prop deferred `connection` (docs/08): `online: null` berarti router tidak terjangkau. */
export type CustomerConnection = {
    online: boolean | null;
    error: string | null;
};

function ConnectionBadge({
    connection,
}: {
    connection: CustomerConnection | undefined;
}) {
    if (connection === undefined) {
        return null;
    }

    const [tone, label]: [StatusTone, string] =
        connection.online === null
            ? ['warning', connection.error ?? 'Router tidak terjangkau']
            : connection.online
              ? ['success', 'Online']
              : ['neutral', 'Offline'];

    return (
        <Badge variant="outline" className={cn(toneClasses[tone])}>
            {label}
        </Badge>
    );
}

/**
 * Data koneksi dan langganan. Status sesi PPPoE dimuat setelah halaman tampil agar
 * halaman tidak menunggu router.
 */
export default function CustomerConnectionCard({
    customer,
    connection,
}: {
    customer: Customer;
    connection: CustomerConnection | undefined;
}) {
    const subscription = customer.subscription;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Koneksi & langganan</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid gap-4">
                    <DetailRow label="Status sesi">
                        <Deferred
                            data="connection"
                            fallback={
                                <Skeleton
                                    className="h-5 w-24"
                                    aria-label="Memeriksa status sesi"
                                />
                            }
                        >
                            <ConnectionBadge connection={connection} />
                        </Deferred>
                    </DetailRow>
                    <div className="grid grid-cols-2 gap-4">
                        <DetailRow label="Router">
                            {customer.router?.name ?? '—'}
                        </DetailRow>
                        <DetailRow label="Username PPPoE">
                            <span className="font-mono">
                                {customer.pppoe_username}
                            </span>
                        </DetailRow>
                    </div>

                    {subscription ? (
                        <>
                            <DetailRow label="Paket">
                                {subscription.package
                                    ? `${subscription.package.name} · ${subscription.package.speed_label}`
                                    : '—'}
                                <span className="block text-muted-foreground">
                                    <Money amount={subscription.price} /> per
                                    bulan
                                </span>
                            </DetailRow>
                            {subscription.next_package ? (
                                <DetailRow label="Ganti paket periode berikutnya">
                                    {subscription.next_package.name} ·{' '}
                                    {subscription.next_package.speed_label}
                                </DetailRow>
                            ) : null}
                            <div className="grid grid-cols-2 gap-4">
                                <DetailRow label="Tanggal tagih">
                                    Setiap tanggal {subscription.billing_day}
                                </DetailRow>
                                <DetailRow label="Mulai berlangganan">
                                    {subscription.starts_at
                                        ? formatDate(subscription.starts_at)
                                        : 'Menunggu pemasangan'}
                                </DetailRow>
                            </div>
                        </>
                    ) : (
                        <DetailRow label="Langganan">
                            Tidak ada langganan aktif.
                        </DetailRow>
                    )}
                </dl>
            </CardContent>
        </Card>
    );
}
