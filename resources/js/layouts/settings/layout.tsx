import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editBilling } from '@/routes/settings/billing';
import { edit as editBusiness } from '@/routes/settings/business';
import { index as messageTemplatesIndex } from '@/routes/settings/message-templates';
import type { NavGroup } from '@/types';

const navGroups: NavGroup[] = [
    {
        title: 'Akun',
        items: [
            { title: 'Profil', href: edit() },
            { title: 'Keamanan', href: editSecurity() },
            { title: 'Tampilan', href: editAppearance() },
        ],
    },
    {
        title: 'Usaha',
        items: [
            {
                title: 'Profil usaha',
                href: editBusiness(),
                permission: 'settings.manage',
            },
            {
                title: 'Aturan tagihan',
                href: editBilling(),
                permission: 'settings.manage',
            },
            {
                title: 'Template WA',
                href: messageTemplatesIndex(),
                permission: 'settings.manage',
            },
        ],
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
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
        <div className="px-4 py-6">
            <Heading
                title="Pengaturan"
                description={
                    can('settings.manage')
                        ? 'Kelola akun Anda serta profil usaha, aturan tagihan, dan pesan WhatsApp'
                        : 'Kelola profil, keamanan, dan tampilan akun Anda'
                }
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col gap-4"
                        aria-label="Pengaturan"
                    >
                        {visibleGroups.map((group) => (
                            <div
                                key={group.title}
                                className="flex flex-col gap-1"
                            >
                                {visibleGroups.length > 1 ? (
                                    <p className="px-3 text-xs font-medium text-muted-foreground">
                                        {group.title}
                                    </p>
                                ) : null}
                                {group.items.map((item) => (
                                    <Button
                                        key={toUrl(item.href)}
                                        variant="ghost"
                                        asChild
                                        className={cn(
                                            'h-10 w-full justify-start lg:h-8',
                                            {
                                                'bg-muted':
                                                    isCurrentOrParentUrl(
                                                        item.href,
                                                    ),
                                            },
                                        )}
                                    >
                                        <Link href={item.href}>
                                            {item.title}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="min-w-0 flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
