import { Inbox } from 'lucide-react';
/** @import { LucideIcon } from 'lucide-react' */
/** @import { ReactNode } from 'react' */

/**
 * @param {{
 *     icon?: LucideIcon;
 *     title: string;
 *     description?: ReactNode;
 *     action?: ReactNode;
 * }} props
 */
export default function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
            <div className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" aria-hidden="true" />
            </div>
            <p className="font-medium">{title}</p>
            {description ? (
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}
            {action ? <div className="mt-2">{action}</div> : null}
        </div>
    );
}
