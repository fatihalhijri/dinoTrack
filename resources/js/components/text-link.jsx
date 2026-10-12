import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';
/** @import { ComponentProps } from 'react' */

/** @typedef {ComponentProps<typeof Link>} Props */

/** @param {Props} props */
export default function TextLink({ className = '', children, ...props }) {
    return (
        <Link
            className={cn(
                'text-foreground underline decoration-muted-foreground/40 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
