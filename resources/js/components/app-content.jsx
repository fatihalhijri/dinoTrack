import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
/** @import { AppVariant } from '@/types' */

/**
 * @typedef {React.ComponentProps<'main'> & {
 *     variant?: AppVariant;
 * }} Props
 */

/** @param {Props} props */
export function AppContent({ variant = 'sidebar', children, ...props }) {
    if (variant === 'sidebar') {
        return <SidebarInset {...props}>{children}</SidebarInset>;
    }

    return (
        <main
            className="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl"
            {...props}
        >
            {children}
        </main>
    );
}
