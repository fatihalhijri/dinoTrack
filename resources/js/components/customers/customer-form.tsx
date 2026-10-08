import { Link } from '@inertiajs/react';
import type { UrlMethodPair } from '@inertiajs/core';
import { Lock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import LocationFields from '@/components/customers/location-fields';
import FormErrorAlert from '@/components/form-error-alert';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { RouterOption } from '@/types';

/** Field bersama form tambah dan ubah. Semua string karena berasal dari input form. */
export type CustomerFormData = {
    name: string;
    phone: string;
    address: string;
    odp: string;
    latitude: string;
    longitude: string;
    router_id: string;
    pppoe_username: string;
    billing_day: string;
    notes: string;
};

export type CustomerField = keyof CustomerFormData;

function FormSection({
    title,
    description,
    children,
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="gap-4 py-4 sm:py-6">
            <CardHeader className="px-4 sm:px-6">
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="flex flex-col gap-4 px-4 sm:px-6">
                {children}
            </CardContent>
        </Card>
    );
}

function OptionalMark() {
    return (
        <span className="font-normal text-muted-foreground">(opsional)</span>
    );
}

/**
 * Isi form tambah/ubah pelanggan, satu kolom di HP. `connectionLockedReason` mengunci
 * router, username PPPoE, dan tanggal tagih (pelanggan yang sudah terpasang, docs/04);
 * `packageField` diisi form tambah karena paket pelanggan lama diganti lewat aksi tersendiri.
 */
export default function CustomerForm({
    data,
    errors,
    onChange,
    onSubmit,
    processing,
    routers,
    packageField,
    connectionLockedReason = null,
    submitLabel,
    cancelHref,
}: {
    data: CustomerFormData;
    errors: Partial<Record<string, string>>;
    onChange: (field: CustomerField, value: string) => void;
    onSubmit: () => void;
    processing: boolean;
    routers: RouterOption[];
    packageField?: ReactNode;
    connectionLockedReason?: string | null;
    submitLabel: string;
    cancelHref: UrlMethodPair;
}) {
    const isConnectionLocked = connectionLockedReason !== null;

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <FormErrorAlert errors={errors} />

            <FormSection title="Data diri">
                <div className="grid gap-2">
                    <Label htmlFor="customer-name">Nama</Label>
                    <Input
                        id="customer-name"
                        value={data.name}
                        onChange={(event) =>
                            onChange('name', event.target.value)
                        }
                        maxLength={255}
                        autoComplete="off"
                        required
                        className="h-10"
                        aria-invalid={errors.name ? true : undefined}
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="customer-phone">Nomor WhatsApp</Label>
                        <Input
                            id="customer-phone"
                            type="tel"
                            inputMode="tel"
                            value={data.phone}
                            onChange={(event) =>
                                onChange('phone', event.target.value)
                            }
                            placeholder="0812xxxxxxxx"
                            autoComplete="off"
                            required
                            className="h-10"
                            aria-describedby="customer-phone-hint"
                            aria-invalid={errors.phone ? true : undefined}
                        />
                        <p
                            id="customer-phone-hint"
                            className="text-xs text-muted-foreground"
                        >
                            Boleh diawali 08, 62, atau +62.
                        </p>
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid content-start gap-2">
                        <Label htmlFor="customer-odp">
                            ODP <OptionalMark />
                        </Label>
                        <Input
                            id="customer-odp"
                            value={data.odp}
                            onChange={(event) =>
                                onChange('odp', event.target.value)
                            }
                            maxLength={100}
                            autoComplete="off"
                            className="h-10"
                            aria-invalid={errors.odp ? true : undefined}
                        />
                        <InputError message={errors.odp} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="customer-address">Alamat</Label>
                    <Textarea
                        id="customer-address"
                        value={data.address}
                        onChange={(event) =>
                            onChange('address', event.target.value)
                        }
                        maxLength={1000}
                        rows={3}
                        required
                        aria-invalid={errors.address ? true : undefined}
                    />
                    <InputError message={errors.address} />
                </div>
            </FormSection>

            <FormSection
                title="Lokasi"
                description="Opsional. Isi keduanya atau kosongkan keduanya."
            >
                <LocationFields
                    latitude={data.latitude}
                    longitude={data.longitude}
                    onChange={(coordinates) => {
                        onChange('latitude', coordinates.latitude);
                        onChange('longitude', coordinates.longitude);
                    }}
                    errors={{
                        latitude: errors.latitude,
                        longitude: errors.longitude,
                    }}
                />
            </FormSection>

            <FormSection title="Koneksi">
                {isConnectionLocked ? (
                    <p className="flex items-start gap-2 rounded-md border bg-muted/50 p-3 text-sm text-muted-foreground">
                        <Lock
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {connectionLockedReason}
                    </p>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <Label htmlFor="customer-router">Router</Label>
                        <Select
                            value={data.router_id}
                            onValueChange={(value) =>
                                onChange('router_id', value)
                            }
                            disabled={
                                isConnectionLocked || routers.length === 0
                            }
                        >
                            <SelectTrigger
                                id="customer-router"
                                className="h-10 w-full"
                                aria-invalid={
                                    errors.router_id ? true : undefined
                                }
                            >
                                <SelectValue placeholder="Pilih router" />
                            </SelectTrigger>
                            <SelectContent>
                                {routers.map((router) => (
                                    <SelectItem
                                        key={router.id}
                                        value={String(router.id)}
                                    >
                                        {router.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {routers.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Belum ada router aktif. Hubungi admin.
                            </p>
                        ) : null}
                        <InputError message={errors.router_id} />
                    </div>

                    <div className="grid content-start gap-2">
                        <Label htmlFor="customer-pppoe">Username PPPoE</Label>
                        <Input
                            id="customer-pppoe"
                            value={data.pppoe_username}
                            onChange={(event) =>
                                onChange('pppoe_username', event.target.value)
                            }
                            maxLength={100}
                            autoComplete="off"
                            autoCapitalize="off"
                            autoCorrect="off"
                            spellCheck={false}
                            required
                            disabled={isConnectionLocked}
                            className="h-10 font-mono"
                            aria-invalid={
                                errors.pppoe_username ? true : undefined
                            }
                        />
                        <InputError message={errors.pppoe_username} />
                    </div>
                </div>

                {packageField}

                <div className="grid gap-2 sm:max-w-[calc(50%-0.5rem)]">
                    <Label htmlFor="customer-billing-day">Tanggal tagih</Label>
                    <Input
                        id="customer-billing-day"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        max={31}
                        step={1}
                        value={data.billing_day}
                        onChange={(event) =>
                            onChange('billing_day', event.target.value)
                        }
                        placeholder={isConnectionLocked ? '—' : '1–31'}
                        required={!isConnectionLocked}
                        disabled={isConnectionLocked}
                        className="h-10"
                        aria-describedby="customer-billing-day-hint"
                        aria-invalid={errors.billing_day ? true : undefined}
                    />
                    <p
                        id="customer-billing-day-hint"
                        className="text-xs text-muted-foreground"
                    >
                        Tagihan terbit setiap bulan pada tanggal ini, bebas dari
                        tanggal pasang. Tanggal 29–31 dibulatkan ke 28.
                    </p>
                    <InputError message={errors.billing_day} />
                </div>
            </FormSection>

            <FormSection title="Catatan">
                <div className="grid gap-2">
                    <Label htmlFor="customer-notes">
                        Catatan <OptionalMark />
                    </Label>
                    <Textarea
                        id="customer-notes"
                        value={data.notes}
                        onChange={(event) =>
                            onChange('notes', event.target.value)
                        }
                        maxLength={2000}
                        rows={3}
                        aria-invalid={errors.notes ? true : undefined}
                    />
                    <InputError message={errors.notes} />
                </div>
            </FormSection>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    asChild
                    type="button"
                    variant="outline"
                    className="h-10"
                >
                    <Link href={cancelHref}>Batal</Link>
                </Button>
                <Button type="submit" className="h-10" disabled={processing}>
                    {processing ? <Spinner /> : null}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
