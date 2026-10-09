import { Head } from '@inertiajs/react';
import { Pencil, Plus, Power, Trash2, UserCog } from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import DataTable from '@/components/data-table';
import EmptyState from '@/components/empty-state';
import FilterBar from '@/components/filter-bar';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import RowActionButton from '@/components/row-action-button';
import StatusBadge from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UserFormDialog from '@/components/users/user-form-dialog';
import { useAuthUser } from '@/hooks/use-auth';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import { useFilters } from '@/hooks/use-filters';
import { formatDate } from '@/lib/format';
import {
    deactivate,
    destroy,
    index as usersIndex,
    reactivate,
} from '@/routes/users';
import type { Paginated, Role, SelectOption, User } from '@/types';

type UserFilters = {
    search?: string;
    role?: string;
    per_page?: string;
};

type UsersIndexProps = {
    users: Paginated<User>;
    filters: UserFilters;
    roles: SelectOption<Role>[];
};

const ALL_ROLES = 'all';

function activeLabel(user: User): string {
    return user.is_active ? 'Aktif' : 'Nonaktif';
}

export default function UsersIndex({
    users,
    filters: initialFilters,
    roles,
}: UsersIndexProps) {
    const authUser = useAuthUser();
    const filters = useFilters<UserFilters>(initialFilters);
    const formDialog = useDialogTarget<User>();
    const toggleDialog = useDialogTarget<User>();
    const deleteDialog = useDialogTarget<User>();

    const isSelf = (user: User): boolean => user.id === authUser.id;

    // Nonaktifkan dan hapus akun sendiri disembunyikan; admin aktif terakhir tetap dijaga backend.
    const actions = (user: User, showLabel = false) => (
        <div
            className={
                showLabel
                    ? 'flex flex-wrap gap-2 *:flex-1'
                    : 'flex items-center justify-end gap-1'
            }
        >
            <RowActionButton
                label="Ubah"
                icon={Pencil}
                showLabel={showLabel}
                onClick={() => formDialog.show(user)}
            />
            {isSelf(user) ? null : (
                <RowActionButton
                    label={user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                    icon={Power}
                    showLabel={showLabel}
                    onClick={() => toggleDialog.show(user)}
                />
            )}
            {!isSelf(user) && user.can_delete ? (
                <RowActionButton
                    label="Hapus"
                    icon={Trash2}
                    showLabel={showLabel}
                    className="text-destructive hover:text-destructive"
                    onClick={() => deleteDialog.show(user)}
                />
            ) : null}
        </div>
    );

    const nameCell = (user: User) => (
        <span className="font-medium break-words">
            {user.name}
            {isSelf(user) ? (
                <span className="font-normal text-muted-foreground">
                    {' '}
                    (Anda)
                </span>
            ) : null}
        </span>
    );

    const roleBadge = (user: User) =>
        user.role_label ? (
            <Badge variant="secondary">{user.role_label}</Badge>
        ) : (
            <span className="text-muted-foreground">Tanpa role</span>
        );

    const statusCell = (user: User) => (
        <div className="flex flex-col items-start gap-1">
            <StatusBadge
                kind="active"
                value={user.is_active}
                label={activeLabel(user)}
            />
            {user.deactivated_at ? (
                <span className="text-xs text-muted-foreground">
                    sejak {formatDate(user.deactivated_at)}
                </span>
            ) : null}
        </div>
    );

    const columns: DataTableColumn<User>[] = [
        { key: 'name', header: 'Nama', cell: nameCell },
        {
            key: 'email',
            header: 'Email',
            cell: (user) => <span className="break-all">{user.email}</span>,
        },
        { key: 'role', header: 'Role', cell: roleBadge },
        { key: 'status', header: 'Status', cell: statusCell },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            cell: (user) => actions(user),
        },
    ];

    const toggleTarget = toggleDialog.item;
    const deleteTarget = deleteDialog.item;

    return (
        <>
            <Head title="Pengguna" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Pengguna"
                    description="Akun admin, kasir, dan teknisi. Pegawai yang keluar dinonaktifkan agar jejak auditnya tetap utuh."
                    actions={
                        <Button
                            className="h-10"
                            onClick={() => formDialog.show(null)}
                        >
                            <Plus />
                            Tambah pengguna
                        </Button>
                    }
                />

                <FilterBar
                    filters={filters}
                    searchPlaceholder="Cari nama atau email…"
                >
                    <Select
                        value={filters.filters.role ?? ALL_ROLES}
                        onValueChange={(value) =>
                            filters.setFilter(
                                'role',
                                value === ALL_ROLES ? undefined : value,
                            )
                        }
                    >
                        <SelectTrigger
                            className="h-10 w-full md:w-40"
                            aria-label="Role"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_ROLES}>
                                Semua role
                            </SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FilterBar>

                <DataTable
                    columns={columns}
                    rows={users.data}
                    rowKey={(user) => user.id}
                    rowClassName={(user) =>
                        user.is_active ? undefined : 'text-muted-foreground'
                    }
                    mobileCard={(user) => (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p>{nameCell(user)}</p>
                                    <p className="break-all text-muted-foreground">
                                        {user.email}
                                    </p>
                                </div>
                                {roleBadge(user)}
                            </div>
                            {statusCell(user)}
                            {actions(user, true)}
                        </div>
                    )}
                    emptyState={
                        <EmptyState
                            icon={UserCog}
                            title="Tidak ada pengguna yang cocok"
                            description="Ubah kata kunci atau hapus filter."
                        />
                    }
                />

                <Pagination paginator={users} />
            </div>

            <UserFormDialog
                key={formDialog.key}
                user={formDialog.item}
                roles={roles}
                isSelf={formDialog.item !== null && isSelf(formDialog.item)}
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
                            ? 'Akun tidak bisa masuk lagi dan sesi yang sedang berjalan langsung diputus. Riwayat aktivitasnya tetap tersimpan.'
                            : 'Akun bisa masuk lagi dengan password yang sama.'
                    }
                    action={
                        toggleTarget.is_active
                            ? deactivate(toggleTarget.id)
                            : reactivate(toggleTarget.id)
                    }
                    confirmLabel={
                        toggleTarget.is_active ? 'Nonaktifkan' : 'Aktifkan'
                    }
                    destructive={toggleTarget.is_active}
                />
            ) : null}

            {deleteTarget ? (
                <ConfirmDialog
                    key={deleteDialog.key}
                    open={deleteDialog.open}
                    onOpenChange={deleteDialog.onOpenChange}
                    title={`Hapus ${deleteTarget.name}?`}
                    description="Akun ini belum punya jejak aktivitas sehingga bisa dihapus permanen. Akun yang sudah dipakai harus dinonaktifkan."
                    action={destroy(deleteTarget.id)}
                    confirmLabel="Hapus"
                    destructive
                />
            ) : null}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Pengguna',
            href: usersIndex(),
        },
    ],
};
