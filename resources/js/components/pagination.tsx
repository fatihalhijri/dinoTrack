import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type PaginationProps<T> = {
    paginator: Paginated<T>;
    className?: string;
};

function PageButton({
    url,
    label,
    children,
}: {
    url: string | null;
    label: string;
    children: ReactNode;
}) {
    if (url === null) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="icon" asChild>
            <Link href={url} preserveState aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}

/**
 * Navigasi halaman untuk Paginated<T>. Label dari backend (bahasa Inggris) tidak dipakai;
 * nomor halaman diambil dari `meta.links` tanpa tombol sebelumnya/berikutnya.
 */
export default function Pagination<T>({
    paginator,
    className,
}: PaginationProps<T>) {
    const { meta, links } = paginator;

    if (meta.total === 0) {
        return null;
    }

    const pageLinks = meta.links.slice(1, -1);

    return (
        <nav
            aria-label="Navigasi halaman"
            className={cn(
                'flex flex-col items-center justify-between gap-3 text-sm sm:flex-row',
                className,
            )}
        >
            <p className="text-muted-foreground">
                Menampilkan {formatNumber(meta.from ?? 0)}–
                {formatNumber(meta.to ?? 0)} dari {formatNumber(meta.total)}
            </p>

            {meta.last_page > 1 ? (
                <div className="flex items-center gap-1">
                    <PageButton url={links.prev} label="Halaman sebelumnya">
                        <ChevronLeft />
                    </PageButton>

                    <span className="px-2 text-muted-foreground sm:hidden">
                        Hal {meta.current_page} / {meta.last_page}
                    </span>

                    <div className="hidden items-center gap-1 sm:flex">
                        {pageLinks.map((link, index) =>
                            link.url === null ? (
                                <span
                                    key={`gap-${index}`}
                                    className="px-2 text-muted-foreground"
                                    aria-hidden="true"
                                >
                                    …
                                </span>
                            ) : (
                                <Button
                                    key={link.url}
                                    variant={link.active ? 'default' : 'ghost'}
                                    size="icon"
                                    asChild
                                >
                                    <Link
                                        href={link.url}
                                        preserveState
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        aria-label={`Halaman ${link.label}`}
                                    >
                                        {link.label}
                                    </Link>
                                </Button>
                            ),
                        )}
                    </div>

                    <PageButton url={links.next} label="Halaman berikutnya">
                        <ChevronRight />
                    </PageButton>
                </div>
            ) : null}
        </nav>
    );
}
