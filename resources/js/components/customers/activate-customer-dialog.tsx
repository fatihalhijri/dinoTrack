import { useForm } from '@inertiajs/react';
import ActionFormDialog from '@/components/customers/action-form-dialog';
import FormErrorAlert, {
    NON_FIELD_ERROR_KEYS,
} from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toDateInputValue } from '@/lib/format';
import { activate } from '@/routes/customers';
import type { Customer } from '@/types';

/** `router_id` bukan field di dialog ini (router nonaktif ditolak ActivateNewCustomer). */
const GENERAL_ERROR_KEYS = [...NON_FIELD_ERROR_KEYS, 'router_id'];

/**
 * Tandai pelanggan `pending` terpasang. Tanggal pasang default hari ini (WIB) dan boleh
 * mundur sampai tanggal pendaftaran; batasnya dijaga backend.
 */
export default function ActivateCustomerDialog({
    customer,
    open,
    onOpenChange,
}: {
    customer: Customer;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const today = toDateInputValue();
    const form = useForm({ installed_at: today });

    const submit = (): void => {
        form.submit(activate(customer.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ActionFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Tandai terpasang"
            description="Pelanggan menjadi aktif, secret PPPoE diaktifkan di router, dan tagihan pertama langsung terbit."
            submitLabel="Tandai terpasang"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorAlert errors={form.errors} keys={GENERAL_ERROR_KEYS} />

            <div className="grid gap-2">
                <Label htmlFor="activate-installed-at">Tanggal pasang</Label>
                <Input
                    id="activate-installed-at"
                    type="date"
                    value={form.data.installed_at}
                    onChange={(event) =>
                        form.setData('installed_at', event.target.value)
                    }
                    min={
                        customer.created_at
                            ? toDateInputValue(customer.created_at)
                            : undefined
                    }
                    max={today}
                    required
                    className="h-10"
                    aria-describedby="activate-installed-at-hint"
                    aria-invalid={form.errors.installed_at ? true : undefined}
                />
                <p
                    id="activate-installed-at-hint"
                    className="text-xs text-muted-foreground"
                >
                    Boleh diisi mundur jika baru dicatat sekarang, tetapi tidak
                    sebelum tanggal pendaftaran.
                </p>
                <InputError message={form.errors.installed_at} />
            </div>
        </ActionFormDialog>
    );
}
