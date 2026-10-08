import type { ReactNode } from 'react';
import EmptyState from '@/components/empty-state';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type DataTableColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    align?: 'left' | 'right' | 'center';
    className?: string;
    /** Sembunyikan kolom ini di kartu HP (misalnya kolom aksi yang sudah ada di kartu). */
    hideOnMobile?: boolean;
};

const alignClasses = {
    left: 'text-left',
    right: 'text-right',
    center: 'text-center',
} as const;

/**
 * Tabel daftar: tabel biasa mulai lebar `md`, daftar kartu di bawahnya. Pengurutan dan
 * pagination dikerjakan server, jadi komponen ini hanya menampilkan baris.
 */
export default function DataTable<T>({
    columns,
    rows,
    rowKey,
    mobileCard,
    rowClassName,
    emptyState,
}: {
    columns: DataTableColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** Isi kartu HP. Tanpa ini, kartu menampilkan label: nilai untuk setiap kolom. */
    mobileCard?: (row: T) => ReactNode;
    rowClassName?: (row: T) => string | undefined;
    emptyState?: ReactNode;
}) {
    if (rows.length === 0) {
        return (
            <div className="rounded-xl border bg-card">
                {emptyState ?? <EmptyState title="Belum ada data" />}
            </div>
        );
    }

    return (
        <>
            <div className="hidden rounded-xl border bg-card md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        alignClasses[column.align ?? 'left'],
                                        column.className,
                                    )}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow
                                key={rowKey(row)}
                                className={rowClassName?.(row)}
                            >
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            alignClasses[
                                                column.align ?? 'left'
                                            ],
                                            column.className,
                                        )}
                                    >
                                        {column.cell(row)}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <ul className="flex flex-col gap-3 md:hidden">
                {rows.map((row) => (
                    <li
                        key={rowKey(row)}
                        className={cn(
                            'rounded-xl border bg-card p-4 text-sm',
                            rowClassName?.(row),
                        )}
                    >
                        {mobileCard ? (
                            mobileCard(row)
                        ) : (
                            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
                                {columns
                                    .filter((column) => !column.hideOnMobile)
                                    .map((column) => (
                                        <div
                                            key={column.key}
                                            className="col-span-2 grid grid-cols-subgrid"
                                        >
                                            <dt className="text-muted-foreground">
                                                {column.header}
                                            </dt>
                                            <dd className="min-w-0 text-right break-words">
                                                {column.cell(row)}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}
