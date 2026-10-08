import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const isActive = (item: NavItem): boolean =>
        item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.title} className="px-2 py-1">
                    <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={toUrl(item.href)}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
