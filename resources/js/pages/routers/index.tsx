import { Head, router } from '@inertiajs/react';
import {
    Pencil,
    PlugZap,
    Plus,
    Router as RouterIcon,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import RouterFormDialog from '@/components/routers/router-form-dialog';
import RowActionButton from '@/components/row-action-button';
import StatusBadge from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import { formatDateTime, formatNumber } from '@/lib/format';
import {
    destroy,
    index as routersIndex,
    test as testConnection,
} from '@/routes/routers';
import type { Paginated, Router } from '@/types';

type RoutersIndexProps = {
    routers: Paginated<Router>;
};

function lastConnected(routerItem: Router): string {
    return routerItem.last_connected_at
        ? formatDateTime(routerItem.last_connected_at)
        : 'Belum pernah';
}

/**
 * Halaman ini hanya untuk `routers.manage` (backend menolak role lain), sehingga semua
 * aksi ditampilkan tanpa cek permission tambahan.
 */
export default function RoutersIndex({ routers }: RoutersIndexProps) {
    const formDialog = useDialogTarget<Router>();
    const deleteDialog = useDialogTarget<Router>();
    const [testingId, setTestingId] = useState<number | null>(null);

    // Tes koneksi sinkron (menunggu router); hasilnya datang sebagai flash toast.
    const runTest = (routerItem: Router): void => {
        router.visit(testConnection(routerItem.id), {
            preserveScroll: true,
            onStart: () => setTestingId(routerItem.id),
            onFinish: () => setTestingId(null),
        });
    };

    const actions = (routerItem: Router, showLabel = false) => (
        <div
            className={
                showLabel
                    ? 'grid grid-cols-3 gap-2'
                    : 'flex items-center justify-end gap-1'
            }
        >
            <RowActionButton
                label={testingId === routerItem.id ? 'Menguji…' : 'Tes koneksi'}
                icon={PlugZap}
                showLabel={showLabel}
                loading={testingId === routerItem.id}
                disabled={testingId !== null}
                onClick={() => runTest(routerItem)}
            />
            <RowActionButton
                label="Ubah"
                icon={Pencil}
                showLabel={showLabel}
                onClick={() => formDialog.show(routerItem)}
            />
            <RowActionButton
                label="Hapus"
                icon={Trash2}
                showLabel={showLabel}
                className="text-destructive hover:text-destructive"
                onClick={() => deleteDialog.show(routerItem)}
            />
        </div>
    );

    const columns: DataTableColumn<Router>[] = [
        {
            key: 'name',
            header: 'Nama',
            cell: (routerItem) => (
                <span className="font-medium">{routerItem.name}</span>
            ),
        },
        {
            key: 'address',
            header: 'Host:port',
            cell: (routerItem) => (
                <span className="font-mono text-xs">
                    {routerItem.host}:{routerItem.port}
                </span>
            ),
        },
        {
            key: 'ssl',
            header: 'SSL',
            cell: (routerItem) => (routerItem.use_ssl ? 'Ya' : 'Tidak'),
        },
        {
            key: 'isolation_profile',
            header: 'Profil isolir',
            cell: (routerItem) => (
                <span className="font-mono text-xs">
                    {routerItem.isolation_profile}
                </span>
            ),
        },
        {
            key: 'customers',
            header: 'Pelanggan',
            align: 'right',
            cell: (routerItem) => (
                <span className="tabular-nums">
                    {formatNumber(routerItem.customers_count ?? 0)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (routerItem) => (
                <StatusBadge
                    kind="active"
                    value={routerItem.is_active}
                    label={routerItem.is_active ? 'Aktif' : 'Nonaktif'}
                />
            ),
        },
        {
            key: 'last_connected_at',
            header: 'Terakhir terhubung',
            cell: (routerItem) => (
                <span className="whitespace-nowrap">
                    {lastConnected(routerItem)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            cell: (routerItem) => actions(routerItem),
        },
    ];

    const deleteTarget = deleteDialog.item;

    return (
        <>
            <Head title="Router" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Router"
                    description="Router Mikrotik yang dipakai untuk aktivasi dan isolir pelanggan."
                    actions={
                        <Button
                            className="h-10"
                            onClick={() => formDialog.show(null)}
                        >
                            <Plus />
                            Tambah router
                        </Button>
                    }
                />

                <DataTable
                    columns={columns}
                    rows={routers.data}
                    rowKey={(routerItem) => routerItem.id}
                    mobileCard={(routerItem) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium break-words">
                                        {routerItem.name}
                                    </p>
                                    <p className="font-mono text-xs break-all text-muted-foreground">
                                        {routerItem.host}:{routerItem.port}
                                        {routerItem.use_ssl ? ' · SSL' : ''}
                                    </p>
                                </div>
                                <StatusBadge
                                    kind="active"
                                    value={routerItem.is_active}
                                    label={
                                        routerItem.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'
                                    }
                                />
                            </div>
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                                <dt className="text-muted-foreground">
                                    Profil isolir
                                </dt>
                                <dd className="min-w-0 text-right font-mono text-xs break-all">
                                    {routerItem.isolation_profile}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Pelanggan
                                </dt>
                                <dd className="text-right tabular-nums">
                                    {formatNumber(
                                        routerItem.customers_count ?? 0,
                                    )}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Terakhir terhubung
                                </dt>
                                <dd className="text-right">
                                    {lastConnected(routerItem)}
                                </dd>
                            </dl>
                            {actions(routerItem, true)}
                        </div>
                    )}
                    emptyState={
                        <EmptyState
                            icon={RouterIcon}
                            title="Belum ada router"
                            description="Tambahkan router Mikrotik sebelum mendaftarkan pelanggan."
                            action={
                                <Button
                                    className="h-10"
                                    onClick={() => formDialog.show(null)}
                                >
                                    <Plus />
                                    Tambah router
                                </Button>
                            }
                        />
                    }
                />

                <Pagination paginator={routers} />
            </div>

            <RouterFormDialog
                key={formDialog.key}
                router={formDialog.item}
                open={formDialog.open}
                onOpenChange={formDialog.onOpenChange}
            />

            {deleteTarget ? (
                <ConfirmDialog
                    key={deleteDialog.key}
                    open={deleteDialog.open}
                    onOpenChange={deleteDialog.onOpenChange}
                    title={`Hapus ${deleteTarget.name}?`}
                    description="Router yang masih punya pelanggan (termasuk yang sudah dihapus) tidak bisa dihapus; nonaktifkan sebagai gantinya."
                    action={destroy(deleteTarget.id)}
                    confirmLabel="Hapus"
                    destructive
                />
            ) : null}
        </>
    );
}

RoutersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Router',
            href: routersIndex(),
        },
    ],
};
