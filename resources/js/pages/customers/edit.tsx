import { Head, useForm } from '@inertiajs/react';
import type { CustomerFormData } from '@/components/customers/customer-form';
import CustomerForm from '@/components/customers/customer-form';
import PageHeader from '@/components/page-header';
import { formatPhone } from '@/lib/format';
import {
    edit,
    index as customersIndex,
    show,
    update,
} from '@/routes/customers';
import type { Customer, RouterOption } from '@/types';

type CustomersEditProps = {
    customer: Customer;
    routers: RouterOption[];
};

export default function CustomersEdit({
    customer,
    routers,
}: CustomersEditProps) {
    const isPending = customer.status === 'pending';
    const billingDay = customer.subscription?.billing_day;
    const form = useForm<CustomerFormData>({
        name: customer.name,
        phone: formatPhone(customer.phone),
        address: customer.address,
        odp: customer.odp ?? '',
        latitude: customer.latitude ?? '',
        longitude: customer.longitude ?? '',
        router_id: String(customer.router?.id ?? ''),
        pppoe_username: customer.pppoe_username,
        billing_day: billingDay === undefined ? '' : String(billingDay),
        notes: customer.notes ?? '',
    });

    const submit = (): void => {
        // Tanggal tagih hanya dikirim selama `pending`; pelanggan berhenti tidak punya
        // subscription aktif, sehingga nilai apa pun akan ditolak UpdateCustomer.
        form.transform(({ billing_day, ...data }) =>
            isPending ? { ...data, billing_day } : data,
        );
        form.submit(update(customer.id));
    };

    return (
        <>
            <Head title={`Ubah ${customer.name}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Ubah pelanggan"
                    description={`${customer.code} · ${customer.name}`}
                />

                <CustomerForm
                    data={form.data}
                    errors={form.errors}
                    onChange={(field, value) => form.setData(field, value)}
                    onSubmit={submit}
                    processing={form.processing}
                    routers={routers}
                    connectionLockedReason={
                        isPending
                            ? null
                            : `Router, username PPPoE, dan tanggal tagih hanya bisa diubah selama pelanggan menunggu pemasangan. Status pelanggan ini: ${customer.status_label}.`
                    }
                    submitLabel="Simpan perubahan"
                    cancelHref={show(customer.id)}
                />
            </div>
        </>
    );
}

CustomersEdit.layout = ({ customer }: CustomersEditProps) => ({
    breadcrumbs: [
        {
            title: 'Pelanggan',
            href: customersIndex(),
        },
        {
            title: customer.name,
            href: show(customer.id),
        },
        {
            title: 'Ubah',
            href: edit(customer.id),
        },
    ],
});
