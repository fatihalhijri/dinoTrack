import { useForm } from '@inertiajs/react';
import ActionFormDialog from '@/components/action-form-dialog';
import FormErrorAlert from '@/components/form-error-alert';
import PackageSelect from '@/components/package-select';
import { formatPeriod } from '@/lib/format';
import { reissue } from '@/routes/invoices';
import type { Invoice, PackageOption } from '@/types';

/**
 * Terbit ulang periode yang tagihannya dibatalkan (docs/04 "Status invoice"). Paket koreksi
 * hanya diisi jika pembatalan karena salah input paket; backend lalu mengarahkan ke tagihan baru.
 */
export default function ReissueInvoiceDialog({
    invoice,
    packages,
    open,
    onOpenChange,
}: {
    invoice: Invoice;
    packages: PackageOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ package_id: '' });

    const submit = (): void => {
        form.submit(reissue(invoice.id), {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ActionFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Terbitkan ulang tagihan"
            description={`Tagihan baru untuk periode ${formatPeriod(invoice.period_start, invoice.period_end)} terbit hari ini dengan nomor dan jatuh tempo baru. Nominal dihitung ulang dari langganan saat ini.`}
            submitLabel="Terbitkan ulang"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorAlert errors={form.errors} />

            <PackageSelect
                id="reissue-package"
                label="Paket koreksi (opsional)"
                value={form.data.package_id}
                onChange={(value) => form.setData('package_id', value)}
                packages={packages}
                noneLabel="Tanpa koreksi paket"
                error={form.errors.package_id}
                hint="Isi hanya jika tagihan dibatalkan karena salah input paket. Paket dan harga langganan langsung dikoreksi untuk periode ini dan seterusnya."
            />
        </ActionFormDialog>
    );
}
