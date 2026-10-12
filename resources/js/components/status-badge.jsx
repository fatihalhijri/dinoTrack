import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
/**
 * @import {
 *     CustomerStatus,
 *     InvoiceStatus,
 *     MessageStatus,
 *     PaymentChargeStatus,
 *     PaymentReviewStatus,
 * } from '@/types'
 */

/** @typedef {'neutral' | 'info' | 'success' | 'warning' | 'danger'} StatusTone */

/**
 * `kind: 'active'` = status aktif master data (paket, router, user); label "Aktif"/"Nonaktif"
 * dari pemanggil.
 *
 * @typedef {{ label: string; className?: string } & (
 *     | { kind: 'customer'; value: CustomerStatus }
 *     | { kind: 'invoice'; value: InvoiceStatus }
 *     | { kind: 'charge'; value: PaymentChargeStatus }
 *     | { kind: 'review'; value: PaymentReviewStatus }
 *     | { kind: 'message'; value: MessageStatus }
 *     | { kind: 'active'; value: boolean }
 * )} StatusBadgeProps
 */

/** @type {Record<CustomerStatus, StatusTone>} */
const customerTones = {
    pending: 'info',
    active: 'success',
    isolated: 'danger',
    terminated: 'neutral',
};

/** @type {Record<InvoiceStatus, StatusTone>} */
const invoiceTones = {
    unpaid: 'warning',
    paid: 'success',
    overdue: 'danger',
    cancelled: 'neutral',
};

/** @type {Record<PaymentChargeStatus, StatusTone>} */
const chargeTones = {
    pending: 'info',
    settled: 'success',
    expired: 'neutral',
    failed: 'danger',
};

/** @type {Record<PaymentReviewStatus, StatusTone>} */
const reviewTones = {
    none: 'neutral',
    needs_review: 'warning',
    resolved: 'success',
};

/** @type {Record<MessageStatus, StatusTone>} */
const messageTones = {
    queued: 'info',
    sent: 'success',
    failed: 'danger',
};

/** @type {Record<StatusTone, string>} */
export const toneClasses = {
    neutral: 'border-border bg-muted text-muted-foreground',
    info: 'border-info/30 bg-info/10 text-info',
    success: 'border-success/30 bg-success/10 text-success',
    warning: 'border-warning/30 bg-warning/10 text-warning',
    danger: 'border-destructive/30 bg-destructive/10 text-destructive dark:text-destructive-foreground',
};

/**
 * @param {StatusBadgeProps} props
 * @returns {StatusTone}
 */
function toneOf(props) {
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
 *
 * @param {StatusBadgeProps} props
 */
export default function StatusBadge(props) {
    return (
        <Badge
            variant="outline"
            className={cn(toneClasses[toneOf(props)], props.className)}
        >
            {props.label}
        </Badge>
    );
}
