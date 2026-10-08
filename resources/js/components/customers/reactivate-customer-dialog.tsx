import { useForm } from '@inertiajs/react';
import ActionFormDialog from '@/components/customers/action-form-dialog';
import PackageSelect from '@/components/customers/package-select';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { reactivate } from '@/routes/customers';
import type { Customer, PackageOption } from '@/types';

/**
 * Daftarkan kembali pelanggan `terminated`: status kembali `pending` dengan langganan baru.
 */
export default function ReactivateCustomerDialog({
    customer,
    packages,
    open,
    onOpenChange,
}: {
    customer: Customer;
    packages: PackageOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ package_id: '', billing_day: '' });

    const submit = (): void => {
        form.submit(reactivate(customer.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ActionFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Daftar kembali"
            description="Pelanggan kembali menunggu pemasangan dengan langganan baru. Kode pelanggan dan riwayat tetap. Tandai terpasang setelah teknisi selesai memasang."
            submitLabel="Daftar kembali"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorAlert errors={form.errors} />

            <PackageSelect
                id="reactivate-package"
                value={form.data.package_id}
                onChange={(value) => form.setData('package_id', value)}
                packages={packages}
                error={form.errors.package_id}
            />

            <div className="grid gap-2">
                <Label htmlFor="reactivate-billing-day">Tanggal tagih</Label>
                <Input
                    id="reactivate-billing-day"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={31}
                    step={1}
                    value={form.data.billing_day}
                    onChange={(event) =>
                        form.setData('billing_day', event.target.value)
                    }
                    placeholder="1–31"
                    required
                    className="h-10 sm:max-w-40"
                    aria-describedby="reactivate-billing-day-hint"
                    aria-invalid={form.errors.billing_day ? true : undefined}
                />
                <p
                    id="reactivate-billing-day-hint"
                    className="text-xs text-muted-foreground"
                >
                    Tagihan terbit setiap bulan pada tanggal ini, bebas dari
                    tanggal pasang. Tanggal 29–31 dibulatkan ke 28.
                </p>
                <InputError message={form.errors.billing_day} />
            </div>
        </ActionFormDialog>
    );
}
