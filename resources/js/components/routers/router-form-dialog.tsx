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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { store, update } from '@/routes/routers';
import type { Router } from '@/types';

const API_PORT = '8728';
const API_SSL_PORT = '8729';

type RouterFormData = {
    name: string;
    host: string;
    port: string;
    username: string;
    password: string;
    use_ssl: boolean;
    isolation_profile: string;
    is_active: boolean;
};

function initialData(router: Router | null): RouterFormData {
    return {
        name: router?.name ?? '',
        host: router?.host ?? '',
        port: router ? String(router.port) : API_PORT,
        username: router?.username ?? '',
        // Password tidak pernah dikirim backend; kosong saat ubah = password lama dipertahankan.
        password: '',
        use_ssl: router?.use_ssl ?? false,
        isolation_profile: router?.isolation_profile ?? 'ISOLIR',
        is_active: router?.is_active ?? true,
    };
}

/**
 * Modal tambah (`router` null) atau ubah router Mikrotik. Diberi `key` baru setiap dibuka
 * agar form diisi dari data terbaru.
 */
export default function RouterFormDialog({
    router,
    open,
    onOpenChange,
}: {
    router: Router | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<RouterFormData>(initialData(router));
    const isEditing = router !== null;

    const changeSsl = (useSsl: boolean): void => {
        form.setData((data) => ({
            ...data,
            use_ssl: useSsl,
            // Port bawaan ikut ditukar (api 8728 ↔ api-ssl 8729); port lain dibiarkan.
            port:
                data.port === API_PORT || data.port === API_SSL_PORT
                    ? useSsl
                        ? API_SSL_PORT
                        : API_PORT
                    : data.port,
        }));
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(isEditing ? update(router.id) : store(), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {isEditing ? 'Ubah router' : 'Tambah router'}
                        </DialogTitle>
                        <DialogDescription>
                            Pakai user API khusus dengan hak terbatas, dan
                            batasi akses API hanya dari IP server.
                        </DialogDescription>
                    </DialogHeader>

                    <FormErrorAlert errors={form.errors} />

                    <div className="grid gap-2">
                        <Label htmlFor="router-name">Nama router</Label>
                        <Input
                            id="router-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Router Utama"
                            maxLength={255}
                            required
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <div className="grid gap-2">
                            <Label htmlFor="router-host">Host</Label>
                            <Input
                                id="router-host"
                                value={form.data.host}
                                onChange={(event) =>
                                    form.setData('host', event.target.value)
                                }
                                placeholder="192.168.88.1"
                                maxLength={255}
                                autoCapitalize="off"
                                autoCorrect="off"
                                spellCheck={false}
                                required
                                className="font-mono"
                                aria-invalid={
                                    form.errors.host ? true : undefined
                                }
                            />
                            <InputError message={form.errors.host} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="router-port">Port API</Label>
                            <Input
                                id="router-port"
                                type="text"
                                inputMode="numeric"
                                value={form.data.port}
                                onChange={(event) =>
                                    form.setData(
                                        'port',
                                        event.target.value.replace(/\D/g, ''),
                                    )
                                }
                                maxLength={5}
                                required
                                className="tabular-nums"
                                aria-invalid={
                                    form.errors.port ? true : undefined
                                }
                            />
                            <InputError message={form.errors.port} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="router-username">
                                Username API
                            </Label>
                            <Input
                                id="router-username"
                                value={form.data.username}
                                onChange={(event) =>
                                    form.setData('username', event.target.value)
                                }
                                maxLength={100}
                                autoComplete="off"
                                autoCapitalize="off"
                                autoCorrect="off"
                                spellCheck={false}
                                required
                                aria-invalid={
                                    form.errors.username ? true : undefined
                                }
                            />
                            <InputError message={form.errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="router-password">
                                Password API
                            </Label>
                            <PasswordInput
                                id="router-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                maxLength={255}
                                autoComplete="new-password"
                                required={!isEditing}
                                placeholder={
                                    isEditing ? 'Tidak diubah' : undefined
                                }
                                aria-describedby={
                                    isEditing
                                        ? 'router-password-hint'
                                        : undefined
                                }
                                aria-invalid={
                                    form.errors.password ? true : undefined
                                }
                            />
                            {isEditing ? (
                                <p
                                    id="router-password-hint"
                                    className="text-xs text-muted-foreground"
                                >
                                    Kosongkan jika password tidak diubah.
                                    Password lama tidak ditampilkan.
                                </p>
                            ) : null}
                            <InputError message={form.errors.password} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="router-isolation-profile">
                            Profil isolir
                        </Label>
                        <Input
                            id="router-isolation-profile"
                            value={form.data.isolation_profile}
                            onChange={(event) =>
                                form.setData(
                                    'isolation_profile',
                                    event.target.value,
                                )
                            }
                            maxLength={100}
                            autoCapitalize="off"
                            autoCorrect="off"
                            spellCheck={false}
                            required
                            className="font-mono"
                            aria-describedby="router-isolation-profile-hint"
                            aria-invalid={
                                form.errors.isolation_profile ? true : undefined
                            }
                        />
                        <p
                            id="router-isolation-profile-hint"
                            className="text-xs text-muted-foreground"
                        >
                            Nama PPP profile untuk pelanggan yang diisolir.
                        </p>
                        <InputError message={form.errors.isolation_profile} />
                    </div>

                    <div className="grid gap-3 rounded-lg border p-3">
                        <SwitchField
                            id="router-use-ssl"
                            label="Pakai SSL (api-ssl)"
                            checked={form.data.use_ssl}
                            onCheckedChange={changeSsl}
                            error={form.errors.use_ssl}
                        />
                        <SwitchField
                            id="router-is-active"
                            label="Aktif"
                            description="Router nonaktif tidak bisa dipilih untuk pelanggan baru."
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked)
                            }
                            error={form.errors.is_active}
                        />
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
                            {isEditing ? 'Simpan perubahan' : 'Tambah router'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function SwitchField({
    id,
    label,
    description,
    checked,
    onCheckedChange,
    error,
}: {
    id: string;
    label: string;
    description?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-1">
            <div className="flex min-h-10 items-center justify-between gap-4">
                <div className="grid gap-0.5">
                    <Label htmlFor={id}>{label}</Label>
                    {description ? (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                <Switch
                    id={id}
                    checked={checked}
                    onCheckedChange={onCheckedChange}
                />
            </div>
            <InputError message={error} />
        </div>
    );
}
