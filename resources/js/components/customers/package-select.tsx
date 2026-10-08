import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRupiah } from '@/lib/format';
import type { PackageOption } from '@/types';

/**
 * Pilihan paket aktif (nama · kecepatan · harga) untuk tambah pelanggan, daftar kembali,
 * dan ganti paket. Nilai berupa id paket dalam bentuk string, atau '' jika belum dipilih.
 */
export default function PackageSelect({
    id,
    label = 'Paket',
    value,
    onChange,
    packages,
    error,
    hint,
}: {
    id: string;
    label?: string;
    value: string;
    onChange: (value: string) => void;
    packages: PackageOption[];
    error?: string;
    hint?: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                value={value}
                onValueChange={onChange}
                disabled={packages.length === 0}
            >
                <SelectTrigger
                    id={id}
                    className="h-10 w-full"
                    aria-invalid={error ? true : undefined}
                >
                    <SelectValue placeholder="Pilih paket" />
                </SelectTrigger>
                <SelectContent>
                    {packages.map((packageItem) => (
                        <SelectItem
                            key={packageItem.id}
                            value={String(packageItem.id)}
                        >
                            {packageItem.name} · {packageItem.speed_label} ·{' '}
                            {formatRupiah(packageItem.price)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {packages.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Belum ada paket aktif. Hubungi admin.
                </p>
            ) : null}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
