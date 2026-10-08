import { Head } from '@inertiajs/react';
import {
    Package as PackageIcon,
    Pencil,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import Money from '@/components/money';
import PackageFormDialog from '@/components/packages/package-form-dialog';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import RowActionButton from '@/components/row-action-button';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import { useFilters } from '@/hooks/use-filters';
import { formatNumber } from '@/lib/format';
import {
    activate,
    deactivate,
    destroy,
    index as packagesIndex,
} from '@/routes/packages';
import type { Package, Paginated } from '@/types';

type PackageFilters = {
    search?: string;
    is_active?: string;
    per_page?: string;
};

type PackagesIndexProps = {
    packages: Paginated<Package>;
    filters: PackageFilters;
};

const ALL_STATUSES = 'all';

function activeLabel(isActive: boolean): string {
    return isActive ? 'Aktif' : 'Nonaktif';
}

export default function PackagesIndex({
    packages,
    filters: initialFilters,
}: PackagesIndexProps) {
    const can = useCan();
    const canManage = can('packages.manage');
    const filters = useFilters<PackageFilters>(initialFilters);
    const formDialog = useDialogTarget<Package>();
    const toggleDialog = useDialogTarget<Package>();
    const deleteDialog = useDialogTarget<Package>();

    const actions = (packageItem: Package, showLabel = false) => (
        <div
            className={
                showLabel
                    ? 'grid grid-cols-3 gap-2'
                    : 'flex items-center justify-end gap-1'
            }
        >
            <RowActionButton
                label="Ubah"
                icon={Pencil}
                showLabel={showLabel}
                onClick={() => formDialog.show(packageItem)}
            />
            <RowActionButton
                label={packageItem.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                icon={Power}
                showLabel={showLabel}
                onClick={() => toggleDialog.show(packageItem)}
            />
            <RowActionButton
                label="Hapus"
                icon={Trash2}
                showLabel={showLabel}
                className="text-destructive hover:text-destructive"
                onClick={() => deleteDialog.show(packageItem)}
            />
        </div>
    );

    const columns: DataTableColumn<Package>[] = [
        {
            key: 'name',
            header: 'Nama',
            cell: (packageItem) => (
                <div className="min-w-0">
                    <p className="font-medium">{packageItem.name}</p>
                    {packageItem.description ? (
                        <p className="max-w-xs truncate text-xs text-muted-foreground">
                            {packageItem.description}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'speed',
            header: 'Kecepatan',
            cell: (packageItem) => packageItem.speed_label,
        },
        {
            key: 'price',
            header: 'Harga',
            align: 'right',
            cell: (packageItem) => <Money amount={packageItem.price} />,
        },
        {
            key: 'profile',
            header: 'Profil Mikrotik',
            cell: (packageItem) => (
                <span className="font-mono text-xs">
                    {packageItem.mikrotik_profile}
                </span>
            ),
        },
        {
            key: 'subscriptions',
            header: 'Langganan',
            align: 'right',
            cell: (packageItem) => (
                <span className="tabular-nums">
                    {formatNumber(packageItem.subscriptions_count ?? 0)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (packageItem) => (
                <StatusBadge
                    kind="active"
                    value={packageItem.is_active}
                    label={activeLabel(packageItem.is_active)}
                />
            ),
        },
        ...(canManage
            ? [
                  {
                      key: 'actions',
                      header: 'Aksi',
                      align: 'right' as const,
                      cell: (packageItem: Package) => actions(packageItem),
                  },
              ]
            : []),
    ];

    const toggleTarget = toggleDialog.item;
    const deleteTarget = deleteDialog.item;

    return (
        <>
            <Head title="Paket" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Paket"
                    description="Paket internet beserta harga bulanan dan profil PPPoE di Mikrotik."
                    actions={
                        canManage ? (
                            <Button
                                className="h-10"
                                onClick={() => formDialog.show(null)}
                            >
                                <Plus />
                                Tambah paket
                            </Button>
                        ) : null
                    }
                />

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari nama atau profil Mikrotik…"
                >
                    <Select
                        value={filters.filters.is_active ?? ALL_STATUSES}
                        onValueChange={(value) =>
                            filters.setFilter(
                                'is_active',
                                value === ALL_STATUSES ? undefined : value,
                            )
                        }
                    >
                        <SelectTrigger
                            className="h-10 w-full md:w-40"
                            aria-label="Status paket"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_STATUSES}>
                                Semua status
                            </SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={packages.data}
                    rowKey={(packageItem) => packageItem.id}
                    mobileCard={(packageItem) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium break-words">
                                        {packageItem.name}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {packageItem.speed_label} ·{' '}
                                        <Money amount={packageItem.price} />
                                    </p>
                                </div>
                                <StatusBadge
                                    kind="active"
                                    value={packageItem.is_active}
                                    label={activeLabel(packageItem.is_active)}
                                />
                            </div>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                <dt className="text-muted-foreground">
                                    Profil Mikrotik
                                </dt>
                                <dd className="min-w-0 text-right font-mono text-xs break-all">
                                    {packageItem.mikrotik_profile}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Langganan
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {formatNumber(
                                        packageItem.subscriptions_count ?? 0,
                                    )}
                                </dd>
                            </dl>
                            {packageItem.description ? (
                                <p className="text-xs text-muted-foreground">
                                    {packageItem.description}
                                </p>
                            ) : null}
                            {canManage ? actions(packageItem, true) : null}
                        </div>
                    )}
                    emptyState={
                        filters.hasActiveFilters ? (
                            <EmptyState
                                icon={PackageIcon}
                                title="Tidak ada paket yang cocok"
                                description="Ubah kata kunci atau hapus filter."
                            />
                        ) : (
                            <EmptyState
                                icon={PackageIcon}
                                title="Belum ada paket"
                                description="Paket dipakai saat mendaftarkan pelanggan."
                                action={
                                    canManage ? (
                                        <Button
                                            className="h-10"
                                            onClick={() =>
                                                formDialog.show(null)
                                            }
                                        >
                                            <Plus />
                                            Tambah paket
                                        </Button>
                                    ) : null
                                }
                            />
                        )
                    }
                />

                <Pagination paginator={packages} />
            </div>

            {canManage ? (
                <>
                    <PackageFormDialog
                        key={formDialog.key}
                        packageItem={formDialog.item}
                        open={formDialog.open}
                        onOpenChange={formDialog.onOpenChange}
                    />

                    {toggleTarget ? (
                        <ConfirmDialog
                            key={toggleDialog.key}
                            open={toggleDialog.open}
                            onOpenChange={toggleDialog.onOpenChange}
                            title={
                                toggleTarget.is_active
                                    ? `Nonaktifkan ${toggleTarget.name}?`
                                    : `Aktifkan ${toggleTarget.name}?`
                            }
                            description={
                                toggleTarget.is_active
                                    ? 'Paket nonaktif tidak bisa dipilih untuk pelanggan baru atau ganti paket. Pelanggan yang sudah memakainya tetap ditagih seperti biasa.'
                                    : 'Paket bisa dipilih lagi untuk pelanggan baru dan ganti paket.'
                            }
                            action={
                                toggleTarget.is_active
                                    ? deactivate(toggleTarget.id)
                                    : activate(toggleTarget.id)
                            }
                            confirmLabel={
                                toggleTarget.is_active
                                    ? 'Nonaktifkan'
                                    : 'Aktifkan'
                            }
                        />
                    ) : null}

                    {deleteTarget ? (
                        <ConfirmDialog
                            key={deleteDialog.key}
                            open={deleteDialog.open}
                            onOpenChange={deleteDialog.onOpenChange}
                            title={`Hapus ${deleteTarget.name}?`}
                            description="Paket yang pernah dipakai pelanggan tidak bisa dihapus; nonaktifkan sebagai gantinya."
                            action={destroy(deleteTarget.id)}
                            confirmLabel="Hapus"
                            destructive
                        />
                    ) : null}
                </>
            ) : null}
        </>
    );
}

PackagesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Paket',
            href: packagesIndex(),
        },
    ],
};
