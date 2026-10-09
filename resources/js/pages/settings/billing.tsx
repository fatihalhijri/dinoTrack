import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SwitchField from '@/components/switch-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, update } from '@/routes/settings/billing';
import type { BillingSettings as BillingSettingsValues } from '@/types';

type BillingSettingsProps = {
    billing: BillingSettingsValues;
    limits: { max_due_days: number; max_grace_days: number };
};

type BillingFormData = {
    due_days: string;
    grace_days: string;
    reminder_days_before: string;
    prorate_first_month: boolean;
    auto_isolate: boolean;
    auto_activate: boolean;
};

/** Angka bulat dari input; null jika kosong atau bukan angka (penjelasan tidak ditampilkan). */
function parseDays(value: string): number | null {
    return /^\d+$/.test(value) ? Number(value) : null;
}

function DaysField({
    id,
    label,
    value,
    min,
    max,
    error,
    explanation,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    min: number;
    max?: number;
    error?: string;
    explanation: ReactNode;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                <Input
                    id={id}
                    type="number"
                    inputMode="numeric"
                    min={min}
                    max={max}
                    step={1}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-10 w-24 tabular-nums"
                    required
                    aria-invalid={error ? true : undefined}
                    aria-describedby={`${id}-explanation`}
                />
                <span className="text-sm text-muted-foreground">hari</span>
            </div>
            <p
                id={`${id}-explanation`}
                className="text-sm text-muted-foreground"
            >
                {explanation}
            </p>
            <InputError message={error} />
        </div>
    );
}

export default function BillingSettings({
    billing,
    limits,
}: BillingSettingsProps) {
    const form = useForm<BillingFormData>({
        due_days: String(billing.due_days),
        grace_days: String(billing.grace_days),
        reminder_days_before: String(billing.reminder_days_before),
        prorate_first_month: billing.prorate_first_month,
        auto_isolate: billing.auto_isolate,
        auto_activate: billing.auto_activate,
    });

    const dueDays = parseDays(form.data.due_days);
    const graceDays = parseDays(form.data.grace_days);
    const reminderDays = parseDays(form.data.reminder_days_before);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(update(), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    return (
        <>
            <Head title="Aturan tagihan" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Aturan tagihan"
                    description="Jatuh tempo, toleransi isolir, pengingat WhatsApp, dan otomatisasi."
                />

                <Alert>
                    <Info />
                    <AlertDescription>
                        Perubahan berlaku untuk proses berikutnya. Tagihan yang
                        sudah terbit tidak dihitung ulang.
                    </AlertDescription>
                </Alert>

                <form onSubmit={submit} className="space-y-6">
                    <DaysField
                        id="due-days"
                        label="Jatuh tempo"
                        value={form.data.due_days}
                        min={1}
                        max={limits.max_due_days}
                        error={form.errors.due_days}
                        onChange={(value) => form.setData('due_days', value)}
                        explanation={
                            dueDays === null
                                ? `Isi 1–${limits.max_due_days} hari.`
                                : `Tagihan jatuh tempo ${dueDays} hari setelah terbit.`
                        }
                    />

                    <DaysField
                        id="grace-days"
                        label="Toleransi sebelum isolir"
                        value={form.data.grace_days}
                        min={0}
                        max={limits.max_grace_days}
                        error={form.errors.grace_days}
                        onChange={(value) => form.setData('grace_days', value)}
                        explanation={
                            graceDays === null
                                ? `Isi 0–${limits.max_grace_days} hari.`
                                : graceDays === 0
                                  ? 'Tanpa toleransi: pelanggan yang belum membayar diisolir sehari setelah jatuh tempo.'
                                  : `Toleransi ${graceDays} hari: pelanggan yang belum membayar diisolir setelah lewat ${graceDays} hari dari jatuh tempo.`
                        }
                    />

                    <DaysField
                        id="reminder-days"
                        label="Pengingat sebelum jatuh tempo"
                        value={form.data.reminder_days_before}
                        min={0}
                        max={dueDays === null ? undefined : dueDays - 1}
                        error={form.errors.reminder_days_before}
                        onChange={(value) =>
                            form.setData('reminder_days_before', value)
                        }
                        explanation={
                            reminderDays === null
                                ? 'Isi 0 sampai kurang dari jumlah hari jatuh tempo.'
                                : reminderDays === 0
                                  ? 'Hanya pengingat pada hari jatuh tempo, pukul 09.00.'
                                  : `Pengingat WhatsApp dikirim ${reminderDays} hari sebelum jatuh tempo dan pada hari jatuh tempo, pukul 09.00.`
                        }
                    />

                    <div className="space-y-2 border-t pt-6">
                        <SwitchField
                            id="prorate-first-month"
                            label="Prorata bulan pertama"
                            description="Tagihan pertama dihitung dari tanggal pasang sampai tanggal tagih berikutnya. Jika mati, tagihan pertama sebesar harga paket penuh."
                            checked={form.data.prorate_first_month}
                            onCheckedChange={(checked) =>
                                form.setData('prorate_first_month', checked)
                            }
                            error={form.errors.prorate_first_month}
                        />
                        <SwitchField
                            id="auto-isolate"
                            label="Isolir otomatis"
                            description="Setiap pukul 01.15, pelanggan yang tagihannya lewat toleransi diisolir di router."
                            checked={form.data.auto_isolate}
                            onCheckedChange={(checked) =>
                                form.setData('auto_isolate', checked)
                            }
                            error={form.errors.auto_isolate}
                        />
                        <SwitchField
                            id="auto-activate"
                            label="Aktivasi otomatis setelah lunas"
                            description="Pelanggan yang diisolir otomatis langsung aktif kembali setelah tunggakannya lunas. Isolir manual tetap hanya dibuka admin."
                            checked={form.data.auto_activate}
                            onCheckedChange={(checked) =>
                                form.setData('auto_activate', checked)
                            }
                            error={form.errors.auto_activate}
                        />
                    </div>

                    <Button
                        type="submit"
                        className="h-10"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing ? <Spinner /> : null}
                        Simpan aturan tagihan
                    </Button>
                </form>
            </div>
        </>
    );
}

BillingSettings.layout = {
    breadcrumbs: [
        {
            title: 'Aturan tagihan',
            href: edit(),
        },
    ],
};
