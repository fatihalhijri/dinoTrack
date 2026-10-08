import { Head, useForm } from '@inertiajs/react';
import type { CustomerFormData } from '@/components/customers/customer-form';
import CustomerForm from '@/components/customers/customer-form';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/format';
import {
    create as customersCreate,
    index as customersIndex,
    store,
} from '@/routes/customers';
import type { PackageOption, RouterOption } from '@/types';

type CreateCustomerFormData = CustomerFormData & { package_id: string };

type CustomersCreateProps = {
    packages: PackageOption[];
    routers: RouterOption[];
};

export default function CustomersCreate({
    packages,
    routers,
}: CustomersCreateProps) {
    const form = useForm<CreateCustomerFormData>({
        name: '',
        phone: '',
        address: '',
        odp: '',
        latitude: '',
        longitude: '',
        // Satu-satunya router aktif langsung dipilih agar teknisi tidak perlu membuka dropdown.
        router_id: routers.length === 1 ? String(routers[0].id) : '',
        pppoe_username: '',
        package_id: '',
        billing_day: '',
        notes: '',
    });

    const packageField = (
        <div className="grid gap-2">
            <Label htmlFor="customer-package">Paket</Label>
            <Select
                value={form.data.package_id}
                onValueChange={(value) => form.setData('package_id', value)}
                disabled={packages.length === 0}
            >
                <SelectTrigger
                    id="customer-package"
                    className="h-10 w-full"
                    aria-invalid={form.errors.package_id ? true : undefined}
                >
                    <SelectValue placeholder="Pilih paket" />
                </SelectTrigger>
                <SelectContent>
                    {packages.map((packageItem) => (
                        <SelectItem
                            key={packageItem.id}
                            value={String(packageItem.id)}
                        >
                            {packageItem.name} · {packageItem.speed_label} ·{' '}
                            {formatRupiah(packageItem.price)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {packages.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Belum ada paket aktif. Hubungi admin.
                </p>
            ) : null}
            <InputError message={form.errors.package_id} />
        </div>
    );

    return (
        <>
            <Head title="Tambah pelanggan" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Tambah pelanggan"
                    description="Pelanggan baru berstatus menunggu pemasangan dan belum ditagih sampai ditandai terpasang."
                />

                <CustomerForm
                    data={form.data}
                    errors={form.errors}
                    onChange={(field, value) => form.setData(field, value)}
                    onSubmit={() => form.submit(store())}
                    processing={form.processing}
                    routers={routers}
                    packageField={packageField}
                    submitLabel="Daftarkan pelanggan"
                    cancelHref={customersIndex()}
                />
            </div>
        </>
    );
}

CustomersCreate.layout = {
    breadcrumbs: [
        {
            title: 'Pelanggan',
            href: customersIndex(),
        },
        {
            title: 'Tambah',
            href: customersCreate(),
        },
    ],
};
