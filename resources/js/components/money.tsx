import { cn } from '@/lib/utils';
import { formatRupiah } from '@/lib/format';

export default function Money({
    amount,
    className,
}: {
    amount: number;
    className?: string;
}) {
    return (
        <span className={cn('whitespace-nowrap tabular-nums', className)}>
            {formatRupiah(amount)}
        </span>
    );
}
