import { Head, Link } from '@inertiajs/react';
import { Plus, TriangleAlert, Users } from 'lucide-react';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCan } from '@/hooks/use-can';
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime, formatPhone } from '@/lib/format';
import { create, index as customersIndex, show } from '@/routes/customers';
import type {
    Customer,
    CustomerStatus,
    PackageOption,
    Paginated,
    RouterOption,
    SelectOption,
} from '@/types';

type CustomerFilters = {
    search?: string;
    status?: string;
    package_id?: string;
    router_id?: string;
    network_error?: string;
    per_page?: string;
};

type CustomersIndexProps = {
    customers: Paginated<Customer>;
    filters: CustomerFilters;
    statuses: SelectOption<CustomerStatus>[];
    packages: PackageOption[];
    routers: RouterOption[];
};

const ALL = 'all';

/** Nilai `network_error` yang dibaca backend sebagai true (rule `boolean`). */
const NETWORK_ERROR_ON = '1';

function networkErrorText(customer: Customer): string {
    return `${customer.network_error ?? 'Perintah router gagal.'} (${formatDateTime(customer.network_error_at)})`;
}

function packageText(customer: Customer): string | null {
    const packageItem = customer.subscription?.package;

    return packageItem
        ? `${packageItem.name} · ${packageItem.speed_label}`
        : null;
}

function CustomerStatusCell({ customer }: { customer: Customer }) {
    return (
        <div className="flex flex-col items-start gap-1">
            <StatusBadge
                kind="customer"
                value={customer.status}
                label={customer.status_label}
            />
            {customer.isolation_reason_label ? (
                <span className="text-xs text-muted-foreground">
                    {customer.isolation_reason_label}
                </span>
            ) : null}
        </div>
    );
}

export default function CustomersIndex({
    customers,
    filters: initialFilters,
    statuses,
    packages,
    routers,
}: CustomersIndexProps) {
    const can = useCan();
    const canCreate = can('customers.create');
    const filters = useFilters<CustomerFilters>(initialFilters);
    const isNetworkErrorOnly =
        filters.filters.network_error !== undefined &&
        !['', '0', 'false'].includes(filters.filters.network_error);

    const selectFilter = (
        key: 'status' | 'package_id' | 'router_id',
        value: string,
    ): void => filters.setFilter(key, value === ALL ? undefined : value);

    const addButton = canCreate ? (
        <Button asChild className="h-10">
            <Link href={create()}>
                <Plus />
                Tambah pelanggan
            </Link>
        </Button>
    ) : null;

    const columns: DataTableColumn<Customer>[] = [
        {
            key: 'code',
            header: 'Kode',
            cell: (customer) => (
                <Link
                    href={show(customer.id)}
                    className="font-mono text-xs hover:underline"
                >
                    {customer.code}
                </Link>
            ),
        },
        {
            key: 'name',
            header: 'Nama',
            cell: (customer) => (
                <div className="flex items-center gap-2">
                    <Link
                        href={show(customer.id)}
                        className="font-medium hover:underline"
                    >
                        {customer.name}
                    </Link>
                    {customer.network_error_at ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span
                                    className="text-warning"
                                    role="img"
                                    aria-label={`Galat router: ${networkErrorText(customer)}`}
                                    tabIndex={0}
                                >
                                    <TriangleAlert className="size-4" />
                                </span>
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs">
                                Galat router: {networkErrorText(customer)}
                            </TooltipContent>
                        </Tooltip>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'phone',
            header: 'WhatsApp',
            cell: (customer) => (
                <span className="tabular-nums">
                    {formatPhone(customer.phone)}
                </span>
            ),
        },
        {
            key: 'package',
            header: 'Paket',
            cell: (customer) => packageText(customer) ?? '—',
        },
        {
            key: 'router',
            header: 'Router',
            cell: (customer) => customer.router?.name ?? '—',
        },
        {
            key: 'status',
            header: 'Status',
            cell: (customer) => <CustomerStatusCell customer={customer} />,
        },
    ];

    return (
        <>
            <Head title="Pelanggan" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pelanggan"
                    description="Data pelanggan, paket, dan status koneksinya."
                    actions={addButton}
                />

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari kode, nama, WA, atau PPPoE…"
                >
                    <div className="grid w-full grid-cols-2 gap-2 md:flex md:w-auto md:flex-wrap">
                        <Select
                            value={filters.filters.status ?? ALL}
                            onValueChange={(value) =>
                                selectFilter('status', value)
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-44"
                                aria-label="Status pelanggan"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua status
                                </SelectItem>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.filters.package_id ?? ALL}
                            onValueChange={(value) =>
                                selectFilter('package_id', value)
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-44"
                                aria-label="Paket"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>Semua paket</SelectItem>
                                {packages.map((packageItem) => (
                                    <SelectItem
                                        key={packageItem.id}
                                        value={String(packageItem.id)}
                                    >
                                        {packageItem.name} ·{' '}
                                        {packageItem.speed_label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={filters.filters.router_id ?? ALL}
                            onValueChange={(value) =>
                                selectFilter('router_id', value)
                            }
                        >
                            <SelectTrigger
                                className="h-10 w-full md:w-44"
                                aria-label="Router"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Semua router
                                </SelectItem>
                                {routers.map((router) => (
                                    <SelectItem
                                        key={router.id}
                                        value={String(router.id)}
                                    >
                                        {router.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Toggle
                            variant="outline"
                            size="lg"
                            className="h-10 w-full md:w-auto"
                            pressed={isNetworkErrorOnly}
                            onPressedChange={(pressed) =>
                                filters.setFilter(
                                    'network_error',
                                    pressed ? NETWORK_ERROR_ON : undefined,
                                )
                            }
                        >
                            <TriangleAlert />
                            Galat router
                        </Toggle>
                    </div>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={customers.data}
                    rowKey={(customer) => customer.id}
                    mobileCard={(customer) => (
                        <Link
                            href={show(customer.id)}
                            className="-m-4 flex flex-col gap-2 rounded-xl p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium break-words">
                                        {customer.name}
                                    </p>
                                    <p className="text-muted-foreground">
                                        <span className="font-mono text-xs">
                                            {customer.code}
                                        </span>{' '}
                                        ·{' '}
                                        <span className="tabular-nums">
                                            {formatPhone(customer.phone)}
                                        </span>
                                    </p>
                                </div>
                                <CustomerStatusCell customer={customer} />
                            </div>
                            <p className="break-words text-muted-foreground">
                                {packageText(customer) ?? 'Tanpa paket aktif'}
                                {customer.router
                                    ? ` · ${customer.router.name}`
                                    : null}
                            </p>
                            {customer.network_error_at ? (
                                <p className="flex items-start gap-2 text-xs text-warning">
                                    <TriangleAlert
                                        className="mt-px size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <span className="min-w-0 break-words">
                                        Galat router:{' '}
                                        {networkErrorText(customer)}
                                    </span>
                                </p>
                            ) : null}
                        </Link>
                    )}
                    emptyState={
                        filters.hasActiveFilters ? (
                            <EmptyState
                                icon={Users}
                                title="Tidak ada pelanggan yang cocok"
                                description="Ubah kata kunci atau hapus filter."
                            />
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="Belum ada pelanggan"
                                description="Pelanggan baru berstatus menunggu pemasangan sampai ditandai terpasang."
                                action={addButton}
                            />
                        )
                    }
                />

                <Pagination paginator={customers} />
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Pelanggan',
            href: customersIndex(),
        },
    ],
};
