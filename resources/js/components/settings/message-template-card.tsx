import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import SwitchField from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { update } from '@/routes/settings/message-templates';
import type { MessageTemplate } from '@/types';

type TemplateFormData = {
    body: string;
    is_active: boolean;
};

/**
 * Panjang menurut code point, sama dengan `max` validasi Laravel (mb_strlen); `length`
 * JavaScript menghitung emoji sebagai dua karakter.
 */
function characterCount(text: string): number {
    const surrogatePairs = text.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g);

    return text.length - (surrogatePairs?.length ?? 0);
}

/** Placeholder yang tidak dikenal dibiarkan apa adanya, sama dengan MessageTemplateRenderer. */
function renderPreview(body: string, examples: Record<string, string>): string {
    return Object.entries(examples).reduce(
        (text, [placeholder, example]) => text.split(placeholder).join(example),
        body,
    );
}

/**
 * Satu template WhatsApp: isi pesan dengan chip placeholder (disisipkan di posisi kursor),
 * penghitung karakter, pratinjau dengan data contoh dari backend, dan status aktif.
 */
export default function MessageTemplateCard({
    template,
    placeholders,
    examples,
    maxLength,
}: {
    template: MessageTemplate;
    placeholders: Record<string, string>;
    examples: Record<string, string>;
    maxLength: number;
}) {
    const form = useForm<TemplateFormData>({
        body: template.body,
        is_active: template.is_active,
    });
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const bodyId = `template-${template.id}-body`;
    const count = characterCount(form.data.body);
    const isTooLong = count > maxLength;

    const insertPlaceholder = (placeholder: string): void => {
        const textarea = textareaRef.current;
        const body = form.data.body;
        const start = textarea?.selectionStart ?? body.length;
        const end = textarea?.selectionEnd ?? body.length;
        const caret = start + placeholder.length;

        form.setData(
            'body',
            body.slice(0, start) + placeholder + body.slice(end),
        );

        // Fokus dan kursor dikembalikan setelah React menulis nilai baru ke textarea.
        requestAnimationFrame(() => {
            textarea?.focus();
            textarea?.setSelectionRange(caret, caret);
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(update(template.id), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <Card>
            <form onSubmit={submit}>
                <CardHeader>
                    <CardTitle className="text-base">
                        {template.label}
                    </CardTitle>
                    <CardDescription>
                        {template.updated_at
                            ? `Terakhir diubah ${formatDateTime(template.updated_at)}`
                            : 'Belum pernah diubah'}
                    </CardDescription>
                </CardHeader>

                <CardContent className="flex flex-col gap-4">
                    <SwitchField
                        id={`template-${template.id}-active`}
                        label="Kirim pesan ini"
                        description={
                            form.data.is_active
                                ? 'Aktif: pesan dikirim otomatis sesuai pemicunya.'
                                : 'Nonaktif: pesan tidak dikirim dan tidak tercatat di log pesan.'
                        }
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked)
                        }
                        error={form.errors.is_active}
                    />

                    <div className="grid gap-2">
                        <div className="flex items-end justify-between gap-2">
                            <Label htmlFor={bodyId}>Isi pesan</Label>
                            <span
                                className={cn(
                                    'text-xs tabular-nums',
                                    isTooLong
                                        ? 'font-medium text-destructive'
                                        : 'text-muted-foreground',
                                )}
                                aria-live="polite"
                            >
                                {formatNumber(count)} /{' '}
                                {formatNumber(maxLength)}
                            </span>
                        </div>
                        <Textarea
                            ref={textareaRef}
                            id={bodyId}
                            value={form.data.body}
                            onChange={(event) =>
                                form.setData('body', event.target.value)
                            }
                            rows={6}
                            required
                            aria-invalid={
                                form.errors.body || isTooLong ? true : undefined
                            }
                        />
                        <InputError message={form.errors.body} />
                        <div
                            className="flex flex-wrap gap-2"
                            aria-label="Sisipkan placeholder"
                        >
                            {Object.entries(placeholders).map(
                                ([placeholder, meaning]) => (
                                    <Tooltip key={placeholder}>
                                        <TooltipTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="h-10 font-mono text-xs md:h-8"
                                                onClick={() =>
                                                    insertPlaceholder(
                                                        placeholder,
                                                    )
                                                }
                                                aria-label={`Sisipkan ${placeholder}: ${meaning}`}
                                            >
                                                {placeholder}
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {meaning}
                                        </TooltipContent>
                                    </Tooltip>
                                ),
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <p className="text-sm font-medium">Pratinjau</p>
                        <div className="rounded-lg border bg-muted/40 p-3 text-sm break-words whitespace-pre-wrap">
                            {form.data.body.trim() === '' ? (
                                <span className="text-muted-foreground">
                                    Isi pesan masih kosong.
                                </span>
                            ) : (
                                renderPreview(form.data.body, examples)
                            )}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Memakai data contoh; pesan asli diisi data pelanggan
                            dan tagihan.
                        </p>
                    </div>

                    <div>
                        <Button
                            type="submit"
                            className="h-10"
                            disabled={
                                form.processing || !form.isDirty || isTooLong
                            }
                        >
                            {form.processing ? <Spinner /> : null}
                            Simpan template
                        </Button>
                    </div>
                </CardContent>
            </form>
        </Card>
    );
}
