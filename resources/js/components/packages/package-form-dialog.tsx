import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import RupiahInput from '@/components/rupiah-input';
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
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/packages';
import type { Package } from '@/types';

type PackageFormData = {
    name: string;
    speed_label: string;
    price: number | null;
    mikrotik_profile: string;
    description: string;
};

function initialData(packageItem: Package | null): PackageFormData {
    return {
        name: packageItem?.name ?? '',
        speed_label: packageItem?.speed_label ?? '',
        price: packageItem?.price ?? null,
        mikrotik_profile: packageItem?.mikrotik_profile ?? '',
        description: packageItem?.description ?? '',
    };
}

/**
 * Modal tambah (`packageItem` null) atau ubah paket. Status aktif tidak diubah di sini,
 * melainkan lewat aksi Aktifkan/Nonaktifkan. Diberi `key` baru setiap dibuka agar form
 * diisi dari data terbaru.
 */
export default function PackageFormDialog({
    packageItem,
    open,
    onOpenChange,
}: {
    packageItem: Package | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<PackageFormData>(initialData(packageItem));
    const isEditing = packageItem !== null;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(isEditing ? update(packageItem.id) : store(), {
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
                            {isEditing ? 'Ubah paket' : 'Tambah paket'}
                        </DialogTitle>
                        <DialogDescription>
                            Profil Mikrotik harus sama persis dengan nama PPP
                            profile di router.
                        </DialogDescription>
                    </DialogHeader>

                    <FormErrorAlert errors={form.errors} />

                    <div className="grid gap-2">
                        <Label htmlFor="package-name">Nama paket</Label>
                        <Input
                            id="package-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Home 20 Mbps"
                            maxLength={255}
                            required
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="package-speed">Kecepatan</Label>
                            <Input
                                id="package-speed"
                                value={form.data.speed_label}
                                onChange={(event) =>
                                    form.setData(
                                        'speed_label',
                                        event.target.value,
                                    )
                                }
                                placeholder="20 Mbps"
                                maxLength={50}
                                required
                                aria-invalid={
                                    form.errors.speed_label ? true : undefined
                                }
                            />
                            <InputError message={form.errors.speed_label} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="package-price">
                                Harga per bulan
                            </Label>
                            <RupiahInput
                                id="package-price"
                                value={form.data.price}
                                onChange={(value) =>
                                    form.setData('price', value)
                                }
                                placeholder="150.000"
                                required
                                aria-invalid={
                                    form.errors.price ? true : undefined
                                }
                            />
                            <InputError message={form.errors.price} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="package-profile">Profil Mikrotik</Label>
                        <Input
                            id="package-profile"
                            value={form.data.mikrotik_profile}
                            onChange={(event) =>
                                form.setData(
                                    'mikrotik_profile',
                                    event.target.value,
                                )
                            }
                            placeholder="Home-20"
                            maxLength={100}
                            autoCapitalize="off"
                            autoCorrect="off"
                            spellCheck={false}
                            required
                            className="font-mono"
                            aria-invalid={
                                form.errors.mikrotik_profile ? true : undefined
                            }
                        />
                        <InputError message={form.errors.mikrotik_profile} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="package-description">
                            Deskripsi{' '}
                            <span className="font-normal text-muted-foreground">
                                (opsional)
                            </span>
                        </Label>
                        <Textarea
                            id="package-description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={1000}
                            rows={3}
                            aria-invalid={
                                form.errors.description ? true : undefined
                            }
                        />
                        <InputError message={form.errors.description} />
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
                            {isEditing ? 'Simpan perubahan' : 'Tambah paket'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
