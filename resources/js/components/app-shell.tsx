import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
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
