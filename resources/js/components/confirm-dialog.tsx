import type { UrlMethodPair } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
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

/** Nilai sederhana yang dikirim bersama konfirmasi (id, tanggal, pilihan). */
export type ConfirmDialogData = Record<
    string,
    string | number | boolean | null
>;

export type ConfirmDialogReason = {
    /** Nama field yang dikirim (default `reason`). */
    field?: string;
    label: string;
    placeholder?: string;
    required?: boolean;
    /** Panjang minimal; default 5 sesuai aturan alasan di backend. */
    minLength?: number;
};

/**
 * Dialog konfirmasi untuk aksi satu langkah (hapus, nonaktifkan, isolir, batalkan, ...).
 * Mengirim `data` + alasan opsional ke route Wayfinder lewat useForm, menampilkan error
 * field alasan di bawah input dan error lain (penolakan Action) sebagai pesan umum.
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
}: {
    trigger: ReactNode;
    title: string;
    description?: ReactNode;
    action: UrlMethodPair;
    confirmLabel?: string;
    destructive?: boolean;
    reason?: ConfirmDialogReason;
    data?: ConfirmDialogData;
    onSuccess?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const reasonField = reason?.field ?? 'reason';
    const form = useForm<ConfirmDialogData>(
        reason ? { ...data, [reasonField]: '' } : { ...data },
    );
    const reasonValue = form.data[reasonField];

    const otherErrorKeys = Object.keys(form.errors).filter(
        (key) => key !== reasonField,
    );

    const changeOpen = (next: boolean): void => {
        setOpen(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
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
            <DialogTrigger asChild>{trigger}</DialogTrigger>
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
                            <Button type="button" variant="outline">
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
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
