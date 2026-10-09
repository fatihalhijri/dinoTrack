import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { CustomerReference } from '@/types';

/**
 * Chip filter `customer_id` di halaman daftar (tautan "Lihat semua …" dari detail pelanggan).
 * `customer` bernilai `null` jika pelanggan dari URL tidak ditemukan.
 */
export default function CustomerFilterChip({
    prefix,
    customer,
    onClear,
}: {
    /** Awal kalimat, misalnya "Tagihan milik". */
    prefix: string;
    customer: CustomerReference | null;
    onClear: () => void;
}) {
    return (
        <div className="flex h-10 max-w-full items-center gap-1 self-start rounded-full border bg-card pr-0 pl-4 text-sm">
            <span className="min-w-0 truncate">
                {prefix}{' '}
                <span className="font-medium">
                    {customer
                        ? `${customer.code} – ${customer.name}`
                        : 'pelanggan tidak ditemukan'}
                </span>
            </span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-10 shrink-0 rounded-full"
                aria-label="Hapus filter pelanggan"
                onClick={onClear}
            >
                <X />
            </Button>
        </div>
    );
}
