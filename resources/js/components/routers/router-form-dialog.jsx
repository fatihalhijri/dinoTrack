import { useForm } from '@inertiajs/react';

import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import SwitchField from '@/components/switch-field';
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

import { store, update } from '@/routes/routers';
/** @import { FormEvent } from 'react' */
/** @import { Router } from '@/types' */

/**
 * @typedef {object} RouterFormData
 * @property {string} name
 * @property {string} host
 * @property {string} port
 * @property {string} username
 * @property {string} password
 * @property {boolean} use_ssl
 * @property {string} isolation_profile
 * @property {boolean} is_active
 */

const API_PORT = '8728';
const API_SSL_PORT = '8729';

/**
 * @param {Router | null} router
 * @returns {RouterFormData}
 */
function initialData(router) {
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
 *
 * @param {{
 *     router: Router | null;
 *     open: boolean;
 *     onOpenChange: (open: boolean) => void;
 * }} props
 */
export default function RouterFormDialog({ router, open, onOpenChange }) {
    const form = useForm(initialData(router));
    const isEditing = router !== null;

    /**
     * @param {boolean} useSsl
     * @returns {void}
     */
    const changeSsl = (useSsl) => {
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

    /**
     * @param {FormEvent<HTMLFormElement>} event
     * @returns {void}
     */
    const submit = (event) => {
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
