import { Link } from '@inertiajs/react';
import {
    BarChart3,
    FileText,
    LayoutGrid,
    Package,
    Router,
    Settings,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import { index as customersIndex } from '@/routes/customers';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as packagesIndex } from '@/routes/packages';
import { index as paymentsIndex } from '@/routes/payments';
import { index as reportsIndex } from '@/routes/reports';
import { index as routersIndex } from '@/routes/routers';
import { edit as businessSettingsEdit } from '@/routes/settings/business';
import { index as usersIndex } from '@/routes/users';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        title: 'Utama',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
                exact: true,
            },
        ],
    },
    {
        title: 'Operasional',
        items: [
            {
                title: 'Pelanggan',
                href: customersIndex(),
                icon: Users,
                permission: 'customers.view',
            },
            {
                title: 'Tagihan',
                href: invoicesIndex(),
                icon: FileText,
                permission: 'invoices.view',
            },
            {
                title: 'Pembayaran',
                href: paymentsIndex(),
                icon: Wallet,
                permission: 'payments.view',
            },
        ],
    },
    {
        title: 'Master',
        items: [
            {
                title: 'Paket',
                href: packagesIndex(),
                icon: Package,
                permission: 'packages.view',
            },
            {
                title: 'Router',
                href: routersIndex(),
                icon: Router,
                permission: 'routers.manage',
            },
        ],
    },
    {
        title: 'Laporan',
        items: [
            {
                title: 'Pendapatan & tunggakan',
                href: reportsIndex(),
                icon: BarChart3,
                permission: 'reports.view',
            },
        ],
    },
    {
        title: 'Admin',
        items: [
            {
                title: 'Pengguna',
                href: usersIndex(),
                icon: UserCog,
                permission: 'users.manage',
            },
            {
                title: 'Pengaturan',
                href: businessSettingsEdit(),
                icon: Settings,
                permission: 'settings.manage',
            },
        ],
    },
];

export function AppSidebar() {
    const can = useCan();

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => item.permission === undefined || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={visibleGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
