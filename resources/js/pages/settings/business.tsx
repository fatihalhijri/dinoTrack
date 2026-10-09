import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import BusinessLogoForm from '@/components/settings/business-logo-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { formatPhone } from '@/lib/format';
import { edit, update } from '@/routes/settings/business';
import type { BusinessProfile } from '@/types';

type BusinessSettingsProps = {
    business: BusinessProfile;
    default_name: string;
    logo_url: string | null;
    logo_max_kilobytes: number;
};

type BusinessFormData = {
    name: string;
    address: string;
    whatsapp: string;
};

export default function BusinessSettings({
    business,
    default_name: defaultName,
    logo_url: logoUrl,
    logo_max_kilobytes: logoMaxKilobytes,
}: BusinessSettingsProps) {
    const form = useForm<BusinessFormData>({
        name: business.name ?? '',
        address: business.address ?? '',
        whatsapp: business.whatsapp ? formatPhone(business.whatsapp) : '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.submit(update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Profil usaha" />

            <BusinessLogoForm
                logoUrl={logoUrl}
                businessName={business.name ?? defaultName}
                maxKilobytes={logoMaxKilobytes}
            />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profil usaha"
                    description="Dipakai di halaman publik pelanggan dan tampilan cetak tagihan."
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="business-name">Nama usaha</Label>
                        <Input
                            id="business-name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder={defaultName}
                            maxLength={100}
                            aria-invalid={form.errors.name ? true : undefined}
                        />
                        <p className="text-sm text-muted-foreground">
                            Jika dikosongkan, dipakai “{defaultName}”.
                        </p>
                        <InputError message={form.errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="business-address">
                            Alamat{' '}
                            <span className="font-normal text-muted-foreground">
                                (opsional)
                            </span>
                        </Label>
                        <Textarea
                            id="business-address"
                            value={form.data.address}
                            onChange={(event) =>
                                form.setData('address', event.target.value)
                            }
                            maxLength={500}
                            rows={3}
                            aria-invalid={
                                form.errors.address ? true : undefined
                            }
                        />
                        <InputError message={form.errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="business-whatsapp">
                            Nomor WhatsApp admin{' '}
                            <span className="font-normal text-muted-foreground">
                                (opsional)
                            </span>
                        </Label>
                        <Input
                            id="business-whatsapp"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            value={form.data.whatsapp}
                            onChange={(event) =>
                                form.setData('whatsapp', event.target.value)
                            }
                            placeholder="0812-3456-7890"
                            aria-invalid={
                                form.errors.whatsapp ? true : undefined
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            Tombol kontak di halaman isolir. Boleh diawali 08
                            atau 62.
                        </p>
                        <InputError message={form.errors.whatsapp} />
                    </div>

                    <Button
                        type="submit"
                        className="h-10"
                        disabled={form.processing}
                    >
                        {form.processing ? <Spinner /> : null}
                        Simpan profil usaha
                    </Button>
                </form>
            </div>
        </>
    );
}

BusinessSettings.layout = {
    breadcrumbs: [
        {
            title: 'Profil usaha',
            href: edit(),
        },
    ],
};
