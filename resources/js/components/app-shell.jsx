import { usePage } from '@inertiajs/react';

import { SidebarProvider } from '@/components/ui/sidebar';
/** @import { ReactNode } from 'react' */
/** @import { AppVariant } from '@/types' */

/**
 * @typedef {object} Props
 * @property {ReactNode} children
 * @property {AppVariant} [variant]
 */

/** @param {Props} props */
export function AppShell({ children, variant = 'sidebar' }) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    // Sidebar ikut disembunyikan saat halaman dicetak (misalnya rincian tagihan).
    return (
        <SidebarProvider
            defaultOpen={isOpen}
            className="print:[&_[data-slot=sidebar]]:hidden"
        >
            {children}
        </SidebarProvider>
    );
}
