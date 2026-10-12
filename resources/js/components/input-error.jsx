import { cn } from '@/lib/utils';
/** @import { HTMLAttributes } from 'react' */

/** @param {HTMLAttributes<HTMLParagraphElement> & { message?: string }} props */
export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'text-sm text-destructive dark:text-destructive-foreground',
                className,
            )}
        >
            {message}
        </p>
    ) : null;
}
