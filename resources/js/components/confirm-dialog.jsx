import { useForm } from '@inertiajs/react';

import { useState } from 'react';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
/** @import { UrlMethodPair } from '@inertiajs/core' */
/** @import { FormEvent, ReactNode } from 'react' */

/**
 * Nilai sederhana yang dikirim bersama konfirmasi (id, tanggal, pilihan).
 *
 * @typedef {Record<string, string | number | boolean | null>} ConfirmDialogData
 */

/**
 * @typedef {object} ConfirmDialogReason
 * @property {string} [field] Nama field yang dikirim (default `reason`).
 * @property {string} label
 * @property {string} [placeholder]
 * @property {boolean} [required]
 * @property {number} [minLength] Panjang minimal; default 5 sesuai aturan alasan di backend.
 * @property {number} [maxLength] Panjang maksimal sesuai validasi backend; tanpa batas jika kosong.
 */

/**
 * Dialog konfirmasi untuk aksi satu langkah (hapus, nonaktifkan, isolir, batalkan, ...).
 * Mengirim `data` + alasan opsional ke route Wayfinder lewat useForm, menampilkan error
 * field alasan di bawah input dan error lain (penolakan Action) sebagai pesan umum.
 *
 * Tanpa `open`, dialog dibuka oleh `trigger`. Dengan `open` + `onOpenChange`, satu dialog
 * dipakai bersama oleh semua baris tabel (lihat useDialogTarget).
 *
 * @param {{
 *     trigger?: ReactNode;
 *     title: string;
 *     description?: ReactNode;
 *     action: UrlMethodPair;
 *     confirmLabel?: string;
 *     destructive?: boolean;
 *     reason?: ConfirmDialogReason;
 *     data?: ConfirmDialogData;
 *     onSuccess?: () => void;
 *     open?: boolean;
 *     onOpenChange?: (open: boolean) => void;
 * }} props
 */
export default function ConfirmDialog({
    trigger,
    title,
    description,
    action,
    confirmLabel = 'Lanjutkan',
    destructive = false,
    reason,
    data = {},
    onSuccess,
    open: controlledOpen,
    onOpenChange,
}) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const open = controlledOpen ?? uncontrolledOpen;
    const reasonField = reason?.field ?? 'reason';
    const form = useForm(
        /** @type {ConfirmDialogData} */ (
            reason ? { ...data, [reasonField]: '' } : { ...data }
        ),
    );
    const reasonValue = form.data[reasonField];

    const otherErrorKeys = Object.keys(form.errors).filter(
        (key) => key !== reasonField,
    );

    /** @param {boolean} next */
    const changeOpen = (next) => {
        setUncontrolledOpen(next);
        onOpenChange?.(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    };

    /** @param {FormEvent<HTMLFormElement>} event */
    const submit = (event) => {
        event.preventDefault();

        form.submit(action, {
            preserveScroll: true,
            onSuccess: () => {
                changeOpen(false);
                onSuccess?.();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            {trigger ? <DialogTrigger asChild>{trigger}</DialogTrigger> : null}
            <DialogContent>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    <FormErrorAlert
                        errors={form.errors}
                        keys={otherErrorKeys}
                    />

                    {reason ? (
                        <div className="grid gap-2">
                            <Label htmlFor="confirm-dialog-reason">
                                {reason.label}
                            </Label>
                            <Textarea
                                id="confirm-dialog-reason"
                                value={
                                    typeof reasonValue === 'string'
                                        ? reasonValue
                                        : ''
                                }
                                onChange={(event) =>
                                    form.setData(
                                        reasonField,
                                        event.target.value,
                                    )
                                }
                                placeholder={reason.placeholder}
                                required={reason.required ?? true}
                                minLength={reason.minLength ?? 5}
                                maxLength={reason.maxLength}
                                rows={3}
                                aria-invalid={
                                    form.errors[reasonField] ? true : undefined
                                }
                            />
                            <InputError message={form.errors[reasonField]} />
                        </div>
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10"
                            >
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            className="h-10"
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {confirmLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
