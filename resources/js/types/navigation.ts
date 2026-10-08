import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { Permission } from '@/types/permissions';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Menu disembunyikan jika user tidak punya permission ini. */
    permission?: Permission;
    /** Aktif hanya jika URL persis sama (bukan halaman turunan). */
    exact?: boolean;
};

export type NavGroup = {
    title: string;
    items: NavItem[];
};
