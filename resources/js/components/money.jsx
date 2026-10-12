import { cn } from '@/lib/utils';
import { formatRupiah } from '@/lib/format';

/**
 * @param {{
 *     amount: number;
 *     className?: string;
 * }} props
 */
export default function Money({ amount, className }) {
    return (
        <span className={cn('whitespace-nowrap tabular-nums', className)}>
            {formatRupiah(amount)}
        </span>
    );
}
