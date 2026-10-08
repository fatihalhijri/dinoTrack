import { Head, useForm } from '@inertiajs/react';
import type { CustomerFormData } from '@/components/customers/customer-form';
import CustomerForm from '@/components/customers/customer-form';
import PackageSelect from '@/components/package-select';
import PageHeader from '@/components/page-header';
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
        <PackageSelect
            id="customer-package"
            value={form.data.package_id}
            onChange={(value) => form.setData('package_id', value)}
            packages={packages}
            error={form.errors.package_id}
        />
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
