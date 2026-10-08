import { ExternalLink, MapPin, MessageCircle } from 'lucide-react';
import DetailRow from '@/components/detail-row';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatDateTime, formatPhone } from '@/lib/format';
import type { Customer } from '@/types';

const externalLinkClass =
    'inline-flex min-h-10 items-center gap-1.5 font-medium text-primary underline-offset-4 hover:underline';

/** Data identitas pelanggan: kontak, lokasi, dan tanggal penting. */
export default function CustomerProfileCard({
    customer,
}: {
    customer: Customer;
}) {
    const hasCoordinates =
        customer.latitude !== null && customer.longitude !== null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Data pelanggan</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="grid gap-4">
                    <DetailRow label="WhatsApp">
                        <a
                            href={`https://wa.me/${customer.phone}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={externalLinkClass}
                        >
                            <MessageCircle className="size-4" />
                            {formatPhone(customer.phone)}
                        </a>
                    </DetailRow>
                    <DetailRow label="Alamat">
                        <span className="whitespace-pre-line">
                            {customer.address}
                        </span>
                    </DetailRow>
                    <DetailRow label="ODP">{customer.odp ?? '—'}</DetailRow>
                    <DetailRow label="Koordinat">
                        {hasCoordinates ? (
                            <a
                                href={`https://www.google.com/maps/search/?api=1&query=${customer.latitude},${customer.longitude}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={externalLinkClass}
                            >
                                <MapPin className="size-4" />
                                Buka di peta
                                <ExternalLink className="size-3.5" />
                            </a>
                        ) : (
                            '—'
                        )}
                    </DetailRow>
                    <div className="grid grid-cols-2 gap-4">
                        <DetailRow label="Terdaftar">
                            {formatDate(customer.created_at)}
                        </DetailRow>
                        <DetailRow label="Terpasang">
                            {formatDate(customer.installed_at)}
                        </DetailRow>
                        {customer.isolated_at ? (
                            <DetailRow label="Diisolir sejak">
                                {formatDateTime(customer.isolated_at)}
                            </DetailRow>
                        ) : null}
                        {customer.terminated_at ? (
                            <DetailRow label="Berhenti">
                                {formatDateTime(customer.terminated_at)}
                            </DetailRow>
                        ) : null}
                    </div>
                    {customer.notes ? (
                        <DetailRow label="Catatan">
                            <span className="whitespace-pre-line">
                                {customer.notes}
                            </span>
                        </DetailRow>
                    ) : null}
                </dl>
            </CardContent>
        </Card>
    );
}
