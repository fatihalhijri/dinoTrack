import { Head } from '@inertiajs/react';
import { RouterIcon } from 'lucide-react';
import CustomerActions from '@/components/customers/customer-actions';
import type { CustomerConnection } from '@/components/customers/customer-connection-card';
import CustomerConnectionCard from '@/components/customers/customer-connection-card';
import CustomerHistoryTabs from '@/components/customers/customer-history-tabs';
import CustomerProfileCard from '@/components/customers/customer-profile-card';
import StatusBadge from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { formatDateTime } from '@/lib/format';
import { index as customersIndex, show } from '@/routes/customers';
import type {
    ActivityLog,
    Customer,
    Invoice,
    MessageLog,
    PackageOption,
    Payment,
} from '@/types';

type CustomersShowProps = {
    customer: Customer;
    invoices: Invoice[] | null;
    payments: Payment[] | null;
    messages: MessageLog[] | null;
    activities: ActivityLog[];
    packages: PackageOption[] | null;
    /** Deferred: belum ada sampai router menjawab. */
    connection?: CustomerConnection;
};

export default function CustomersShow({
    customer,
    invoices,
    payments,
    messages,
    activities,
    packages,
    connection,
}: CustomersShowProps) {
    return (
        <>
            <Head title={customer.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="min-w-0 space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight break-words">
                            {customer.name}
                        </h1>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span className="font-mono">{customer.code}</span>
                            <StatusBadge
                                kind="customer"
                                value={customer.status}
                                label={customer.status_label}
                            />
                            {customer.isolation_reason_label ? (
                                <span>
                                    Alasan isolir:{' '}
                                    {customer.isolation_reason_label}
                                </span>
                            ) : null}
                        </div>
                    </div>
                    <CustomerActions
                        customer={customer}
                        packages={packages}
                        hasInvoices={invoices !== null && invoices.length > 0}
                    />
                </div>

                {customer.network_error_at ? (
                    <Alert variant="destructive">
                        <RouterIcon />
                        <AlertTitle>Perintah router gagal</AlertTitle>
                        <AlertDescription>
                            <p>
                                {customer.network_error ??
                                    'Perintah ke router gagal setelah semua percobaan.'}
                            </p>
                            <p className="text-xs">
                                {formatDateTime(customer.network_error_at)} ·
                                Tanda ini hilang setelah perintah router
                                berikutnya berhasil.
                            </p>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex min-w-0 flex-col gap-6">
                        <CustomerConnectionCard
                            customer={customer}
                            connection={connection}
                        />
                        <CustomerProfileCard customer={customer} />
                    </div>
                    <div className="min-w-0 lg:col-span-2">
                        <CustomerHistoryTabs
                            customerId={customer.id}
                            invoices={invoices}
                            payments={payments}
                            messages={messages}
                            activities={activities}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

CustomersShow.layout = ({ customer }: CustomersShowProps) => ({
    breadcrumbs: [
        {
            title: 'Pelanggan',
            href: customersIndex(),
        },
        {
            title: customer.name,
            href: show(customer.id),
        },
    ],
});
