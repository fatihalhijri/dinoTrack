import type { Role } from '@/types/models';
import type { Permission } from '@/types/permissions';

/**
 * User login dari props bersama `auth.user` (HandleInertiaRequests::userData).
 */
export type AuthUser = {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    role_label: string | null;
    email_verified_at: string | null;
    two_factor_enabled: boolean;
};

export type Auth = {
    user: AuthUser | null;
    permissions: Permission[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
