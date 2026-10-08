import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FilterValue, UseFiltersReturn } from '@/hooks/use-filters';

export const PER_PAGE_OPTIONS = [10, 20, 50, 100] as const;
const DEFAULT_PER_PAGE = 20;

type BaseFilters = {
    search?: FilterValue;
    per_page?: FilterValue;
    [key: string]: FilterValue;
};

/**
 * Baris filter halaman daftar: pencarian, filter tambahan (children), jumlah per halaman,
 * dan tombol hapus filter. State dan sinkronisasi URL lewat useFilters().
 */
export default function FilterBar<T extends BaseFilters>({
    filters,
    searchPlaceholder = 'Cari…',
    children,
}: {
    filters: UseFiltersReturn<T>;
    searchPlaceholder?: string;
    children?: ReactNode;
}) {
    const search = filters.filters.search;
    const perPage = filters.filters.per_page ?? DEFAULT_PER_PAGE;

    return (
        <div className="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center">
            <div className="relative md:w-72">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    type="search"
                    value={
                        search === null || search === undefined
                            ? ''
                            : String(search)
                    }
                    onChange={(event) =>
                        filters.setFilter('search', event.target.value)
                    }
                    placeholder={searchPlaceholder}
                    aria-label={searchPlaceholder}
                    className="h-10 pl-9"
                />
            </div>

            {children ? (
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            ) : null}

            <div className="flex items-center gap-2 md:ml-auto">
                {filters.hasActiveFilters ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-10"
                        onClick={filters.reset}
                    >
                        <X />
                        Hapus filter
                    </Button>
                ) : null}
                <Select
                    value={String(perPage)}
                    onValueChange={(value) =>
                        filters.setFilter('per_page', Number(value))
                    }
                >
                    <SelectTrigger
                        className="h-10 w-full md:w-32"
                        aria-label="Jumlah per halaman"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PER_PAGE_OPTIONS.map((option) => (
                            <SelectItem key={option} value={String(option)}>
                                {option} / halaman
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
