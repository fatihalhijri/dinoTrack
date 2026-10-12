/** @import { ReactNode } from 'react' */

/**
 * Bagian halaman bertajuk dengan keterangan atau kontrol kecil di kanan (`aside`).
 *
 * @param {{
 *     title: string;
 *     aside?: ReactNode;
 *     children: ReactNode;
 * }} props
 */
export default function PageSection({ title, aside, children }) {
    return (
        <section className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                <h2 className="text-base font-semibold">{title}</h2>
                {aside ? (
                    <div className="text-xs text-muted-foreground">{aside}</div>
                ) : null}
            </div>
            {children}
        </section>
    );
}
