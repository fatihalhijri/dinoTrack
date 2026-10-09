import { useForm } from '@inertiajs/react';
import { ImageIcon, Trash2, Upload } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { destroy, store } from '@/routes/settings/business/logo';

const ACCEPTED_TYPES = 'image/png,image/jpeg,image/webp';

/**
 * Logo usaha untuk halaman publik pelanggan. Gambar dipilih dulu (pratinjau), lalu diunggah
 * lewat tombol Simpan logo; tipe dan ukuran diperiksa backend dari isi file.
 */
export default function BusinessLogoForm({
    logoUrl,
    businessName,
    maxKilobytes,
}: {
    logoUrl: string | null;
    businessName: string;
    maxKilobytes: number;
}) {
    const form = useForm<{ logo: File | null }>({ logo: null });
    const inputRef = useRef<HTMLInputElement>(null);
    const [inputKey, setInputKey] = useState(0);

    const [selectedPreview, setSelectedPreview] = useState<string | null>(null);

    // URL pratinjau lama dilepas saat diganti atau komponen dilepas.
    useEffect(
        () => () => {
            if (selectedPreview) {
                URL.revokeObjectURL(selectedPreview);
            }
        },
        [selectedPreview],
    );

    const previewUrl = selectedPreview ?? logoUrl;
    const maxLabel =
        maxKilobytes >= 1024 && maxKilobytes % 1024 === 0
            ? `${maxKilobytes / 1024} MB`
            : `${maxKilobytes} KB`;

    const clearSelection = (): void => {
        form.reset();
        form.clearErrors();
        setSelectedPreview(null);
        setInputKey((key) => key + 1);
    };

    const choose = (event: ChangeEvent<HTMLInputElement>): void => {
        const file = event.target.files?.[0] ?? null;

        form.clearErrors();
        form.setData('logo', file);
        setSelectedPreview(file ? URL.createObjectURL(file) : null);
    };

    const upload = (): void => {
        form.submit(store(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: clearSelection,
        });
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Logo usaha"
                description="Tampil di halaman tagihan dan halaman isolir yang dibuka pelanggan."
            />

            {/* Sengaja bukan <form>: submit dari ConfirmDialog (portal) ikut menggelembung lewat
                pohon React ke form induk dan membatalkan permintaan hapus. */}
            <div className="space-y-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="flex h-24 w-full items-center justify-center rounded-lg border bg-muted/40 p-3 sm:w-48">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt={`Logo ${businessName}`}
                                className="max-h-full max-w-full object-contain"
                            />
                        ) : (
                            <div className="flex flex-col items-center gap-1 text-sm text-muted-foreground">
                                <ImageIcon
                                    className="size-6"
                                    aria-hidden="true"
                                />
                                Belum ada logo
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-2">
                        <input
                            key={inputKey}
                            ref={inputRef}
                            id="business-logo"
                            type="file"
                            accept={ACCEPTED_TYPES}
                            className="sr-only"
                            onChange={choose}
                            aria-invalid={form.errors.logo ? true : undefined}
                        />
                        <div className="flex flex-wrap gap-2">
                            {form.data.logo ? (
                                <>
                                    <Button
                                        type="button"
                                        className="h-10"
                                        disabled={form.processing}
                                        onClick={upload}
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Upload />
                                        )}
                                        Simpan logo
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10"
                                        disabled={form.processing}
                                        onClick={clearSelection}
                                    >
                                        Batal
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10"
                                        onClick={() =>
                                            inputRef.current?.click()
                                        }
                                    >
                                        <ImageIcon />
                                        {logoUrl
                                            ? 'Ganti logo'
                                            : 'Pilih gambar'}
                                    </Button>
                                    {logoUrl ? (
                                        <ConfirmDialog
                                            trigger={
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    className="h-10 text-destructive hover:text-destructive"
                                                >
                                                    <Trash2 />
                                                    Hapus logo
                                                </Button>
                                            }
                                            title="Hapus logo usaha?"
                                            description="Halaman publik kembali hanya menampilkan nama usaha."
                                            action={destroy()}
                                            confirmLabel="Hapus"
                                            destructive
                                        />
                                    ) : null}
                                </>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {form.data.logo
                                ? form.data.logo.name
                                : `PNG, JPG, atau WebP, maksimal ${maxLabel}.`}
                        </p>
                        <InputError message={form.errors.logo} />
                    </div>
                </div>
            </div>
        </div>
    );
}
