import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { AuthUser } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: AuthUser;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();
    const subtitle = showEmail ? user.email : user.role_label;

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-lg">
                <AvatarFallback className="rounded-lg bg-sidebar-primary font-medium text-sidebar-primary-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {subtitle ? (
                    <span className="truncate text-xs opacity-70">
                        {subtitle}
                    </span>
                ) : null}
            </div>
        </>
    );
}
