import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Tombol aksi per baris dengan target sentuh 40 px. Di tabel tampil sebagai ikon + tooltip,
 * di kartu HP (`showLabel`) sebagai tombol berlabel.
 */
export default function RowActionButton({
    label,
    icon: Icon,
    showLabel = false,
    loading = false,
    className,
    disabled,
    variant = 'ghost',
    ...props
}: Omit<ComponentProps<typeof Button>, 'children' | 'size'> & {
    label: string;
    icon: LucideIcon;
    showLabel?: boolean;
    loading?: boolean;
}) {
    const icon = loading ? <Spinner /> : <Icon aria-hidden="true" />;

    if (showLabel) {
        return (
            <Button
                type="button"
                variant={variant === 'ghost' ? 'outline' : variant}
                className={cn('h-10', className)}
                disabled={disabled || loading}
                {...props}
            >
                {icon}
                {label}
            </Button>
        );
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant={variant}
                    size="icon"
                    className={cn('size-10', className)}
                    disabled={disabled || loading}
                    aria-label={label}
                    {...props}
                >
                    {icon}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}
