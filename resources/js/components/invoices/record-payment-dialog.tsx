import { useForm } from '@inertiajs/react';
import ActionFormDialog from '@/components/action-form-dialog';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import Money from '@/components/money';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { toDateInputValue } from '@/lib/format';
import { store } from '@/routes/invoices/payments';
import type { Invoice, PaymentMethod, SelectOption } from '@/types';

/**
 * Catat pembayaran tunai/transfer. Nominal tidak diketik: selalu sama dengan total tagihan
 * (docs/04 "Pembayaran manual"), tetap dikirim agar backend memeriksa kecocokannya. Tanggal
 * bayar default hari ini (WIB), boleh mundur sampai tanggal terbit.
 */
export default function RecordPaymentDialog({
    invoice,
    methods,
    open,
    onOpenChange,
}: {
    invoice: Invoice;
    methods: SelectOption<PaymentMethod>[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const today = toDateInputValue();
    const form = useForm({
        method: methods[0]?.value ?? '',
        amount: invoice.total,
        paid_at: today,
        notes: '',
    });

    const submit = (): void => {
        form.submit(store(invoice.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <ActionFormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Catat pembayaran"
            description={`Pembayaran tunai atau transfer untuk ${invoice.number}. Tagihan langsung lunas; isolir karena tunggakan dibuka otomatis jika tidak ada tunggakan lain yang lewat toleransi.`}
            submitLabel="Catat pembayaran"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorAlert errors={form.errors} />

            <div className="grid gap-1 rounded-lg border bg-muted/40 p-3">
                <span className="text-sm text-muted-foreground">
                    Nominal (sama dengan total tagihan)
                </span>
                <Money
                    amount={invoice.total}
                    className="text-2xl font-semibold"
                />
                <InputError message={form.errors.amount} />
            </div>

            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm leading-none font-medium">
                    Metode pembayaran
                </legend>
                <RadioGroup
                    value={form.data.method}
                    onValueChange={(value) => {
                        const selected = methods.find(
                            (method) => method.value === value,
                        );

                        if (selected) {
                            form.setData('method', selected.value);
                        }
                    }}
                    className="grid grid-cols-2 gap-2"
                    aria-invalid={form.errors.method ? true : undefined}
                >
                    {methods.map((method) => (
                        <Label
                            key={method.value}
                            htmlFor={`record-payment-method-${method.value}`}
                            className="flex min-h-10 cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 font-normal has-data-[state=checked]:border-primary has-data-[state=checked]:bg-primary/5"
                        >
                            <RadioGroupItem
                                id={`record-payment-method-${method.value}`}
                                value={method.value}
                            />
                            {method.label}
                        </Label>
                    ))}
                </RadioGroup>
                <InputError message={form.errors.method} />
            </fieldset>

            <div className="grid gap-2">
                <Label htmlFor="record-payment-paid-at">Tanggal bayar</Label>
                <Input
                    id="record-payment-paid-at"
                    type="date"
                    value={form.data.paid_at}
                    onChange={(event) =>
                        form.setData('paid_at', event.target.value)
                    }
                    min={invoice.issued_at}
                    max={today}
                    required
                    className="h-10"
                    aria-describedby="record-payment-paid-at-hint"
                    aria-invalid={form.errors.paid_at ? true : undefined}
                />
                <p
                    id="record-payment-paid-at-hint"
                    className="text-xs text-muted-foreground"
                >
                    Isi tanggal uang diterima jika baru dicatat sekarang; tidak
                    sebelum tanggal terbit tagihan.
                </p>
                <InputError message={form.errors.paid_at} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="record-payment-notes">
                    Catatan{' '}
                    <span className="font-normal text-muted-foreground">
                        (opsional)
                    </span>
                </Label>
                <Textarea
                    id="record-payment-notes"
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    placeholder="Misalnya nama bank pengirim atau nomor bukti transfer"
                    maxLength={1000}
                    rows={3}
                    aria-invalid={form.errors.notes ? true : undefined}
                />
                <InputError message={form.errors.notes} />
            </div>
        </ActionFormDialog>
    );
}
