import type { ChangeEvent, ComponentProps } from 'react';
import { useLayoutEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { formatNumber, parseRupiahInput } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Posisi kursor setelah `digitCount` angka pertama pada teks berformat `150.000`. */
function caretAfterDigits(text: string, digitCount: number): number {
    if (digitCount === 0) {
        return 0;
    }

    let seen = 0;

    for (let index = 0; index < text.length; index++) {
        if (/\d/.test(text.charAt(index))) {
            seen++;

            if (seen === digitCount) {
                return index + 1;
            }
        }
    }

    return text.length;
}

/**
 * Input nominal Rupiah dengan pemisah ribuan. Nilainya integer rupiah (atau null jika
 * kosong), sehingga form mengirim angka, bukan teks berformat.
 */
export default function RupiahInput({
    value,
    onChange,
    className,
    ...props
}: Omit<
    ComponentProps<'input'>,
    'value' | 'onChange' | 'type' | 'inputMode' | 'defaultValue'
> & {
    value: number | null;
    onChange: (value: number | null) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const pendingDigitsBeforeCaret = useRef<number | null>(null);
    const displayValue = value === null ? '' : formatNumber(value);

    // Pemisah ribuan menggeser panjang teks; kursor dikembalikan ke posisi angka yang sama.
    useLayoutEffect(() => {
        const input = inputRef.current;
        const digitCount = pendingDigitsBeforeCaret.current;

        if (input === null || digitCount === null) {
            return;
        }

        pendingDigitsBeforeCaret.current = null;

        if (document.activeElement === input) {
            const caret = caretAfterDigits(displayValue, digitCount);
            input.setSelectionRange(caret, caret);
        }
    }, [displayValue]);

    const change = (event: ChangeEvent<HTMLInputElement>): void => {
        const { value: text, selectionStart } = event.target;

        pendingDigitsBeforeCaret.current = text
            .slice(0, selectionStart ?? text.length)
            .replace(/\D/g, '').length;
        onChange(parseRupiahInput(text));
    };

    return (
        <div className="relative">
            <span
                className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                aria-hidden="true"
            >
                Rp
            </span>
            <Input
                ref={inputRef}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                value={displayValue}
                onChange={change}
                className={cn('pl-10 tabular-nums', className)}
                {...props}
            />
        </div>
    );
}
