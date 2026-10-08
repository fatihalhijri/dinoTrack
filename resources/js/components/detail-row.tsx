import type { ReactNode } from 'react';

/** Pasangan label–nilai di dalam `<dl>` kartu detail (pelanggan, tagihan). */
export default function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-sm break-words">{children}</dd>
        </div>
    );
}
