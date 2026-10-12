import {
    Ban,
    Banknote,
    EllipsisVertical,
    Printer,
    RefreshCw,
    Send,
} from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import RecordPaymentDialog from '@/components/invoices/record-payment-dialog';
import ReissueInvoiceDialog from '@/components/invoices/reissue-invoice-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCan } from '@/hooks/use-can';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import { cancel, resend } from '@/routes/invoices';
/** @import { LucideIcon } from 'lucide-react' */
/** @import { CanFn } from '@/hooks/use-can' */
/** @import { UseDialogTargetReturn } from '@/hooks/use-dialog-target' */
/** @import { Invoice, InvoiceReference, PackageOption, PaymentMethod, SelectOption } from '@/types' */

/** @typedef {'record' | 'print' | 'resend' | 'cancel' | 'reissue'} InvoiceActionKey */

/**
 * @typedef {object} InvoiceAction
 * @property {InvoiceActionKey} key
 * @property {string} label
 * @property {LucideIcon} icon
 * @property {boolean} [primary]
 * @property {boolean} [destructive]
 */

/**
 * Aksi yang valid untuk permission user DAN status tagihan (docs/04 "Status invoice"):
 * catat pembayaran, kirim ulang, dan batalkan hanya untuk tagihan yang belum dibayar
 * (opsi metode hanya dikirim untuk `payments.record`), terbit ulang hanya untuk
 * tagihan batal yang periodenya belum diterbitkan ulang. Penolakan tetap dari backend.
 *
 * @param {Invoice} invoice
 * @param {CanFn} can
 * @param {boolean} canReissue
 * @param {boolean} canRecordPayment
 * @returns {InvoiceAction[]}
 */
function availableActions(invoice, can, canReissue, canRecordPayment) {
    const isOutstanding =
        invoice.status === 'unpaid' || invoice.status === 'overdue';
    /** @type {InvoiceAction[]} */
    const actions = [];

    if (isOutstanding && canRecordPayment) {
        actions.push({
            key: 'record',
            label: 'Catat pembayaran',
            icon: Banknote,
            primary: true,
        });
    }

    if (invoice.status === 'cancelled' && canReissue) {
        actions.push({
            key: 'reissue',
            label: 'Terbitkan ulang',
            icon: RefreshCw,
            primary: true,
        });
    }

    if (isOutstanding && can('invoices.resend')) {
        actions.push({ key: 'resend', label: 'Kirim ulang WA', icon: Send });
    }

    actions.push({ key: 'print', label: 'Cetak', icon: Printer });

    if (isOutstanding && can('invoices.cancel')) {
        actions.push({
            key: 'cancel',
            label: 'Batalkan',
            icon: Ban,
            destructive: true,
        });
    }

    return actions;
}

/**
 * Tombol aksi di header detail tagihan: berjajar mulai lebar `md`, menjadi menu "Aksi" di HP.
 * Semua dialog memakai satu state sehingga hanya satu yang dirender.
 * `paymentMethods`: metode pembayaran manual; `null` tanpa `payments.record`.
 *
 * @param {{
 *     invoice: Invoice;
 *     packages: PackageOption[] | null;
 *     replacement: InvoiceReference | null;
 *     paymentMethods: SelectOption<PaymentMethod>[] | null;
 * }} props
 */
export default function InvoiceActions({
    invoice,
    packages,
    replacement,
    paymentMethods,
}) {
    const can = useCan();
    const dialog = /** @type {UseDialogTargetReturn<InvoiceActionKey>} */ (
        useDialogTarget()
    );
    const actions = availableActions(
        invoice,
        can,
        packages !== null && replacement === null,
        paymentMethods !== null && can('payments.record'),
    );

    /** @param {InvoiceActionKey} key */
    const run = (key) => {
        if (key === 'print') {
            window.print();

            return;
        }

        dialog.show(key);
    };

    const dialogProps = {
        open: dialog.open,
        onOpenChange: dialog.onOpenChange,
    };

    return (
        <div className="print:hidden">
            <div className="hidden flex-wrap items-center justify-end gap-2 md:flex">
                {actions.map((action) => (
                    <Button
                        key={action.key}
                        type="button"
                        variant={action.primary ? 'default' : 'outline'}
                        className={
                            action.destructive
                                ? 'h-10 text-destructive hover:text-destructive'
                                : 'h-10'
                        }
                        onClick={() => run(action.key)}
                    >
                        <action.icon />
                        {action.label}
                    </Button>
                ))}
            </div>

            <div className="md:hidden">
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 w-full"
                        >
                            <EllipsisVertical />
                            Aksi
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                    >
                        {actions.map((action) => (
                            <DropdownMenuItem
                                key={action.key}
                                className="min-h-10"
                                variant={
                                    action.destructive
                                        ? 'destructive'
                                        : 'default'
                                }
                                onSelect={() => run(action.key)}
                            >
                                <action.icon />
                                {action.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {dialog.item === 'record' && paymentMethods !== null ? (
                <RecordPaymentDialog
                    key={dialog.key}
                    invoice={invoice}
                    methods={paymentMethods}
                    {...dialogProps}
                />
            ) : null}

            {dialog.item === 'resend' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title="Kirim ulang tagihan ke WhatsApp?"
                    description={`Pesan "Tagihan terbit" untuk ${invoice.number} dikirim ulang ke nomor WhatsApp ${invoice.customer?.name ?? 'pelanggan'} lewat antrean.`}
                    action={resend(invoice.id)}
                    confirmLabel="Kirim ulang"
                />
            ) : null}

            {dialog.item === 'cancel' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title={`Batalkan ${invoice.number}?`}
                    description="Link bayar tidak bisa dipakai lagi; QRIS yang terlanjur dibayar dicatat sebagai pembayaran perlu tinjauan. Pelanggan yang diisolir otomatis dibuka isolirnya jika tidak ada lagi tunggakan lewat toleransi. Periode ini bisa diterbitkan ulang."
                    action={cancel(invoice.id)}
                    reason={{
                        label: 'Alasan pembatalan',
                        placeholder: 'Minimal 5 karakter',
                        maxLength: 255,
                    }}
                    confirmLabel="Batalkan tagihan"
                    destructive
                />
            ) : null}

            {dialog.item === 'reissue' && packages !== null ? (
                <ReissueInvoiceDialog
                    key={dialog.key}
                    invoice={invoice}
                    packages={packages}
                    {...dialogProps}
                />
            ) : null}
        </div>
    );
}
