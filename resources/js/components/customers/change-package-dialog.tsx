import { useForm } from '@inertiajs/react';
import ActionFormDialog from '@/components/action-form-dialog';
import FormErrorAlert from '@/components/form-error-alert';
import PackageSelect from '@/components/package-select';
import { packageMethod } from '@/routes/customers';
import type { Customer, PackageOption } from '@/types';

/**
 * Ganti paket. Pelanggan `pending` langsung berganti paket; pelanggan terpasang
 * mendapat paket baru mulai tagihan periode berikutnya (docs/04 "Ganti paket").
 * Membatalkan rencana memakai ConfirmDialog dengan `package_id: null`.
 */
export default function ChangePackageDialog({
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
    const subscription = customer.subscription;
    const currentPackageId = subscription?.package?.id;
    const nextPackageId = subscription?.next_package?.id;
    const form = useForm({
        package_id: nextPackageId === undefined ? '' : String(nextPackageId),
    });
    const isPending = customer.status === 'pending';

    const submit = (): void => {
        form.submit(packageMethod(customer.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ActionFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Ganti paket"
            description={
                isPending
                    ? 'Pelanggan belum terpasang, sehingga paket dan harga langsung diganti.'
                    : 'Paket baru berlaku mulai tagihan periode berikutnya, tanpa prorata dan tanpa tagihan selisih. Paket saat ini tetap dipakai sampai saat itu.'
            }
            submitLabel={isPending ? 'Ganti paket' : 'Jadwalkan ganti paket'}
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorAlert errors={form.errors} />

            <PackageSelect
                id="change-package"
                label="Paket baru"
                value={form.data.package_id}
                onChange={(value) => form.setData('package_id', value)}
                packages={packages.filter(
                    (packageItem) => packageItem.id !== currentPackageId,
                )}
                error={form.errors.package_id}
                hint={
                    subscription?.package
                        ? `Paket saat ini: ${subscription.package.name} · ${subscription.package.speed_label}`
                        : undefined
                }
            />
        </ActionFormDialog>
    );
}
