import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
/** @import { ReactNode } from 'react' */

/**
 * @param {{
 *     title?: string;
 *     description?: string;
 *     children: ReactNode;
 * }} props
 */
export default function AuthLayout({ title = '', description = '', children }) {
    return (
        <AuthLayoutTemplate title={title} description={description}>
            {children}
        </AuthLayoutTemplate>
    );
}
