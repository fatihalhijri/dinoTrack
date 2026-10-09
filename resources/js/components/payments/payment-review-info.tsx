import StatusBadge from '@/components/status-badge';
import type { Payment } from '@/types';

/**
 * Status tinjauan pembayaran anomali beserta catatannya (alasan anomali, lalu catatan
 * peninjau). Pembayaran normal tampil sebagai tanda pisah.
 */
export default function PaymentReviewInfo({ payment }: { payment: Payment }) {
    if (payment.review_status === 'none') {
        return '—';
    }

    return (
        <div className="flex flex-col items-start gap-1">
            <StatusBadge
                kind="review"
                value={payment.review_status}
                label={payment.review_status_label}
            />
            {payment.review_note ? (
                <p className="text-xs whitespace-pre-line text-muted-foreground">
                    {payment.review_note}
                </p>
            ) : null}
        </div>
    );
}
