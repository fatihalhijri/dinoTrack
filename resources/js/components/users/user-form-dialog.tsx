import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/users';
import type { Role, SelectOption, User } from '@/types';

type UserFormData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: Role | '';
};

function initialData(user: User | null): UserFormData {
    return {
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        role: user?.role ?? '',
    };
}

/**
 * Modal tambah (`user` null) atau ubah akun pegawai. Saat ubah, password kosong berarti
 * password lama dipertahankan. Role akun sendiri dikunci karena ditolak backend (agar admin
 * tidak mengunci dirinya sendiri dari menu admin).
 */
export default function UserFormDialog({
    user,
    roles,
    isSelf,
    open,
    onOpenChange,
}: {
    user: User | null;
    roles: SelectOption<Role>[];
    isSelf: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<UserFormData>(initialData(user));
    const isEditing = user !== null;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(isEditing ? update(user.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Ubah pengguna' : 'Tambah pengguna'}
                        </DialogTitle>
                        <DialogDescription>
                            {isEditing
                                ? 'Kosongkan password jika tidak ingin menggantinya.'
                                : 'Email langsung terverifikasi. Berikan password awal ini kepada pegawai.'}
                        </DialogDescription>
                    </DialogHeader>

                    <FormErrorAlert errors={form.errors} />

                    <div className="grid gap-2">
                        <Label htmlFor="user-name">Nama</Label>
                        <Input
                            id="user-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                            maxLength={255}
                            required
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-email">Email</Label>
                        <Input
                            id="user-email"
                            type="email"
                            inputMode="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="off"
                            autoCapitalize="off"
                            maxLength={255}
                            required
                            aria-invalid={form.errors.email ? true : undefined}
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="user-role">Role</Label>
                        <Select
                            value={form.data.role}
                            onValueChange={(value) =>
                                form.setData(
                                    'role',
                                    roles.find((role) => role.value === value)
                                        ?.value ?? '',
                                )
                            }
                            disabled={isSelf}
                        >
                            <SelectTrigger
                                id="user-role"
                                className="h-10 w-full"
                                aria-invalid={
                                    form.errors.role ? true : undefined
                                }
                            >
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {isSelf ? (
                            <p className="text-sm text-muted-foreground">
                                Role akun Anda sendiri tidak bisa diubah.
                            </p>
                        ) : null}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="user-password">
                                {isEditing ? 'Password baru' : 'Password'}
                                {isEditing ? (
                                    <span className="font-normal text-muted-foreground">
                                        {' '}
                                        (opsional)
                                    </span>
                                ) : null}
                            </Label>
                            <PasswordInput
                                id="user-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                autoComplete="new-password"
                                required={!isEditing}
                                aria-invalid={
                                    form.errors.password ? true : undefined
                                }
                            />
                            <InputError message={form.errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-password-confirmation">
                                Ulangi password
                            </Label>
                            <PasswordInput
                                id="user-password-confirmation"
                                value={form.data.password_confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                required={
                                    !isEditing || form.data.password !== ''
                                }
                                aria-invalid={
                                    form.errors.password_confirmation
                                        ? true
                                        : undefined
                                }
                            />
                            <InputError
                                message={form.errors.password_confirmation}
                            />
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10"
                            >
                                Batal
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            className="h-10"
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}
                            {isEditing ? 'Simpan perubahan' : 'Tambah pengguna'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
