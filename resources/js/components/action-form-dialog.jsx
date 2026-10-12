import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
/** @import { FormEvent, ReactNode } from 'react' */

/**
 * Kerangka dialog aksi yang punya field sendiri (tandai terpasang, daftar kembali, ganti
 * paket, terbit ulang tagihan). Aksi satu langkah memakai ConfirmDialog.
 *
 * @param {{
 *     open: boolean;
 *     onOpenChange: (open: boolean) => void;
 *     title: string;
 *     description?: ReactNode;
 *     submitLabel: string;
 *     processing: boolean;
 *     onSubmit: () => void;
 *     children: ReactNode;
 * }} props
 */
export default function ActionFormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    children,
}) {
    /** @param {FormEvent<HTMLFormElement>} event */
    const submit = (event) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    {children}

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
                            className="h-10"
                            disabled={processing}
                        >
                            {processing ? <Spinner /> : null}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
