import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
/** @import { ReactNode } from 'react' */
/** @import { BreadcrumbItem } from '@/types' */

/**
 * @param {{
 *     breadcrumbs?: BreadcrumbItem[];
 *     children: ReactNode;
 * }} props
 */
export default function AppLayout({ breadcrumbs = [], children }) {
    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
