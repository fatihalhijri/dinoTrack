import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CircleCheck,
    CircleOff,
    EllipsisVertical,
    Lock,
    LockOpen,
    PackageX,
    Pencil,
    RefreshCw,
    Replace,
    Trash2,
} from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import ActivateCustomerDialog from '@/components/customers/activate-customer-dialog';
import ChangePackageDialog from '@/components/customers/change-package-dialog';
import ReactivateCustomerDialog from '@/components/customers/reactivate-customer-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { CanFn } from '@/hooks/use-can';
import { useCan } from '@/hooks/use-can';
import { useDialogTarget } from '@/hooks/use-dialog-target';
import {
    destroy,
    edit,
    isolate,
    packageMethod,
    release,
    terminate,
} from '@/routes/customers';
import type { Customer, PackageOption } from '@/types';

type CustomerActionKey =
    | 'activate'
    | 'change-package'
    | 'cancel-package-plan'
    | 'isolate'
    | 'release'
    | 'terminate'
    | 'reactivate'
    | 'delete';

type CustomerAction = {
    key: CustomerActionKey;
    label: string;
    icon: LucideIcon;
    primary?: boolean;
    destructive?: boolean;
};

/**
 * Aksi yang valid untuk permission user DAN status pelanggan saat ini (docs/04
 * "Status pelanggan"). Hanya menyembunyikan tombol; penolakan tetap dari backend.
 */
function availableActions(
    customer: Customer,
    can: CanFn,
    hasPackageOptions: boolean,
    hasInvoices: boolean,
): CustomerAction[] {
    const { status } = customer;
    const isInstalled = status === 'active' || status === 'isolated';
    const canChangePackage =
        can('customers.update') && hasPackageOptions && status !== 'terminated';
    const actions: CustomerAction[] = [];

    if (status === 'pending' && can('customers.activate')) {
        actions.push({
            key: 'activate',
            label: 'Tandai terpasang',
            icon: CircleCheck,
            primary: true,
        });
    }

    if (status === 'terminated' && can('customers.terminate')) {
        actions.push({
            key: 'reactivate',
            label: 'Daftar kembali',
            icon: RefreshCw,
            primary: true,
        });
    }

    if (canChangePackage) {
        actions.push({
            key: 'change-package',
            label: 'Ganti paket',
            icon: Replace,
        });

        if (customer.subscription?.next_package) {
            actions.push({
                key: 'cancel-package-plan',
                label: 'Batalkan rencana paket',
                icon: PackageX,
            });
        }
    }

    if (can('customers.isolate')) {
        // Isolir manual boleh menimpa isolir otomatis (alasan menjadi manual).
        if (
            status === 'active' ||
            (status === 'isolated' && customer.isolation_reason === 'overdue')
        ) {
            actions.push({
                key: 'isolate',
                label: 'Isolir manual',
                icon: Lock,
            });
        }

        if (status === 'isolated') {
            actions.push({
                key: 'release',
                label: 'Buka isolir',
                icon: LockOpen,
            });
        }
    }

    if (isInstalled && can('customers.terminate')) {
        actions.push({
            key: 'terminate',
            label: 'Berhentikan',
            icon: CircleOff,
            destructive: true,
        });
    }

    if (status === 'pending' && !hasInvoices && can('customers.delete')) {
        actions.push({
            key: 'delete',
            label: 'Hapus',
            icon: Trash2,
            destructive: true,
        });
    }

    return actions;
}

/**
 * Tombol aksi di header detail pelanggan: berjajar mulai lebar `md`, menjadi menu "Aksi"
 * di HP. Semua dialog memakai satu state sehingga hanya satu yang dirender.
 */
export default function CustomerActions({
    customer,
    packages,
    hasInvoices,
}: {
    customer: Customer;
    packages: PackageOption[] | null;
    hasInvoices: boolean;
}) {
    const can = useCan();
    const dialog = useDialogTarget<CustomerActionKey>();
    const canEdit = can('customers.update');
    const actions = availableActions(
        customer,
        can,
        packages !== null,
        hasInvoices,
    );

    if (!canEdit && actions.length === 0) {
        return null;
    }

    const dialogProps = {
        open: dialog.open,
        onOpenChange: dialog.onOpenChange,
    };

    return (
        <>
            <div className="hidden flex-wrap items-center justify-end gap-2 md:flex">
                {canEdit ? (
                    <Button asChild variant="outline" className="h-10">
                        <Link href={edit(customer.id)}>
                            <Pencil />
                            Ubah
                        </Link>
                    </Button>
                ) : null}
                {actions.map((action) => (
                    <Button
                        key={action.key}
                        type="button"
                        variant={action.primary ? 'default' : 'outline'}
                        className={
                            action.destructive
                                ? 'h-10 text-destructive hover:text-destructive'
                                : 'h-10'
                        }
                        onClick={() => dialog.show(action.key)}
                    >
                        <action.icon />
                        {action.label}
                    </Button>
                ))}
            </div>

            <div className="md:hidden">
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 w-full"
                        >
                            <EllipsisVertical />
                            Aksi
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56"
                    >
                        {canEdit ? (
                            <DropdownMenuItem asChild className="min-h-10">
                                <Link href={edit(customer.id)}>
                                    <Pencil />
                                    Ubah data
                                </Link>
                            </DropdownMenuItem>
                        ) : null}
                        {canEdit && actions.length > 0 ? (
                            <DropdownMenuSeparator />
                        ) : null}
                        {actions.map((action) => (
                            <DropdownMenuItem
                                key={action.key}
                                className="min-h-10"
                                variant={
                                    action.destructive
                                        ? 'destructive'
                                        : 'default'
                                }
                                onSelect={() => dialog.show(action.key)}
                            >
                                <action.icon />
                                {action.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {dialog.item === 'activate' ? (
                <ActivateCustomerDialog
                    customer={customer}
                    key={dialog.key}
                    {...dialogProps}
                />
            ) : null}

            {dialog.item === 'reactivate' && packages !== null ? (
                <ReactivateCustomerDialog
                    customer={customer}
                    packages={packages}
                    key={dialog.key}
                    {...dialogProps}
                />
            ) : null}

            {dialog.item === 'change-package' && packages !== null ? (
                <ChangePackageDialog
                    customer={customer}
                    packages={packages}
                    key={dialog.key}
                    {...dialogProps}
                />
            ) : null}

            {dialog.item === 'cancel-package-plan' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title="Batalkan rencana ganti paket?"
                    description={`Pelanggan tetap memakai ${customer.subscription?.package?.name ?? 'paket saat ini'} pada periode berikutnya.`}
                    action={packageMethod(customer.id)}
                    data={{ package_id: null }}
                    confirmLabel="Batalkan rencana"
                />
            ) : null}

            {dialog.item === 'isolate' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title="Isolir manual"
                    description={
                        customer.status === 'isolated'
                            ? 'Pelanggan sedang diisolir otomatis. Alasannya diganti menjadi manual sehingga pembayaran tidak lagi membuka isolir; hanya admin yang bisa membukanya.'
                            : 'Profil PPPoE diganti ke profil isolir dan sesi diputus lewat antrean. Isolir manual tidak dibuka oleh pembayaran; hanya admin yang bisa membukanya.'
                    }
                    action={isolate(customer.id)}
                    reason={{
                        label: 'Alasan',
                        placeholder: 'Minimal 5 karakter',
                    }}
                    confirmLabel="Isolir"
                    destructive
                />
            ) : null}

            {dialog.item === 'release' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title="Buka isolir"
                    description="Profil paket dipasang kembali di router lewat antrean. Jika pelanggan masih menunggak lewat toleransi, isolir otomatis berikutnya akan mengisolirnya lagi."
                    action={release(customer.id)}
                    reason={{
                        label: 'Alasan',
                        placeholder: 'Minimal 5 karakter',
                    }}
                    confirmLabel="Buka isolir"
                />
            ) : null}

            {dialog.item === 'terminate' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title={`Berhentikan ${customer.name}?`}
                    description="Langganan berakhir hari ini sehingga tagihan berhenti, dan secret PPPoE dinonaktifkan di router. Tagihan yang belum dibayar tetap bisa ditagih."
                    action={terminate(customer.id)}
                    reason={{
                        label: 'Alasan berhenti (opsional)',
                        required: false,
                        minLength: 0,
                    }}
                    confirmLabel="Berhentikan"
                    destructive
                />
            ) : null}

            {dialog.item === 'delete' ? (
                <ConfirmDialog
                    key={dialog.key}
                    {...dialogProps}
                    title={`Hapus ${customer.name}?`}
                    description="Hanya untuk salah input atau batal pasang. Username PPPoE bisa dipakai lagi untuk pendaftaran yang benar."
                    action={destroy(customer.id)}
                    confirmLabel="Hapus"
                    destructive
                />
            ) : null}
        </>
    );
}
