import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type {
    CustomerStatus,
    InvoiceStatus,
    MessageStatus,
    PaymentChargeStatus,
    PaymentReviewStatus,
} from '@/types';

export type StatusTone = 'neutral' | 'info' | 'success' | 'warning' | 'danger';

type StatusBadgeProps = { label: string; className?: string } & (
    | { kind: 'customer'; value: CustomerStatus }
    | { kind: 'invoice'; value: InvoiceStatus }
    | { kind: 'charge'; value: PaymentChargeStatus }
    | { kind: 'review'; value: PaymentReviewStatus }
    | { kind: 'message'; value: MessageStatus }
    /** Status aktif master data (paket, router, user); label "Aktif"/"Nonaktif" dari pemanggil. */
    | { kind: 'active'; value: boolean }
);

const customerTones: Record<CustomerStatus, StatusTone> = {
    pending: 'info',
    active: 'success',
    isolated: 'danger',
    terminated: 'neutral',
};

const invoiceTones: Record<InvoiceStatus, StatusTone> = {
    unpaid: 'warning',
    paid: 'success',
    overdue: 'danger',
    cancelled: 'neutral',
};

const chargeTones: Record<PaymentChargeStatus, StatusTone> = {
    pending: 'info',
    settled: 'success',
    expired: 'neutral',
    failed: 'danger',
};

const reviewTones: Record<PaymentReviewStatus, StatusTone> = {
    none: 'neutral',
    needs_review: 'warning',
    resolved: 'success',
};

const messageTones: Record<MessageStatus, StatusTone> = {
    queued: 'info',
    sent: 'success',
    failed: 'danger',
};

export const toneClasses: Record<StatusTone, string> = {
    neutral: 'border-border bg-muted text-muted-foreground',
    info: 'border-info/30 bg-info/10 text-info',
    success: 'border-success/30 bg-success/10 text-success',
    warning: 'border-warning/30 bg-warning/10 text-warning',
    danger: 'border-destructive/30 bg-destructive/10 text-destructive dark:text-destructive-foreground',
};

function toneOf(props: StatusBadgeProps): StatusTone {
    switch (props.kind) {
        case 'customer':
            return customerTones[props.value];
        case 'invoice':
            return invoiceTones[props.value];
        case 'charge':
            return chargeTones[props.value];
        case 'review':
            return reviewTones[props.value];
        case 'message':
            return messageTones[props.value];
        case 'active':
            return props.value ? 'success' : 'neutral';
    }
}

/**
 * Badge status dengan warna konsisten di semua halaman. Label enum selalu dari `*_label` backend.
 */
export default function StatusBadge(props: StatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(toneClasses[toneOf(props)], props.className)}
        >
            {props.label}
        </Badge>
    );
}
