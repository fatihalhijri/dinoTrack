import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function StatCard({
    title,
    value,
    description,
    icon: Icon,
    href,
    className,
}: {
    title: string;
    value: ReactNode;
    description?: ReactNode;
    icon?: LucideIcon;
    href?: NonNullable<InertiaLinkProps['href']>;
    className?: string;
}) {
    const content = (
        <>
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-muted-foreground">
                    {title}
                </p>
                {Icon ? (
                    <Icon
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                ) : null}
            </div>
            <div className="text-2xl font-semibold tracking-tight tabular-nums">
                {value}
            </div>
            {description ? (
                <p className="text-xs text-muted-foreground">{description}</p>
            ) : null}
        </>
    );

    const classes = cn(
        'flex flex-col gap-2 rounded-xl border bg-card p-4 text-card-foreground shadow-sm',
        className,
    );

    if (href === undefined) {
        return <div className={classes}>{content}</div>;
    }

    return (
        <Link
            href={href}
            prefetch
            className={cn(
                classes,
                'transition-colors hover:border-primary/40 hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
            )}
        >
            {content}
        </Link>
    );
}
