import { LocateFixed } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useGeolocation } from '@/hooks/use-geolocation';
import { formatNumber } from '@/lib/format';

/** Sama dengan presisi kolom `decimal(10,7)`. */
const COORDINATE_DECIMALS = 7;

/**
 * Keypad `inputMode="decimal"` di iOS tidak punya tombol minus (hampir semua lintang di
 * Indonesia negatif) dan memakai koma sebagai pemisah pada locale Indonesia, sehingga
 * koordinat memakai keyboard teks dan koma diubah menjadi titik agar lolos validasi angka.
 */
const COORDINATE_INPUT_MODE = 'text';

/**
 * @param {string} value
 * @returns {string}
 */
function normalizeCoordinate(value) {
    return value.replace(',', '.');
}

/**
 * Latitude/longitude pelanggan (opsional) dengan tombol "Pakai lokasi saya" untuk teknisi
 * yang sedang berada di lokasi pemasangan.
 *
 * @param {{
 *     latitude: string;
 *     longitude: string;
 *     onChange: (coordinates: { latitude: string; longitude: string }) => void;
 *     errors: { latitude?: string; longitude?: string };
 * }} props
 */
export default function LocationFields({
    latitude,
    longitude,
    onChange,
    errors,
}) {
    const geolocation = useGeolocation();
    const [accuracy, setAccuracy] = useState(
        /** @type {number | null} */ (null),
    );

    const useMyLocation = () => {
        geolocation.locate((result) => {
            setAccuracy(result.accuracy);
            onChange({
                latitude: result.latitude.toFixed(COORDINATE_DECIMALS),
                longitude: result.longitude.toFixed(COORDINATE_DECIMALS),
            });
        });
    };

    const message = geolocation.error ?? geolocation.unsupportedReason;

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="customer-latitude">Latitude</Label>
                    <Input
                        id="customer-latitude"
                        inputMode={COORDINATE_INPUT_MODE}
                        value={latitude}
                        onChange={(event) => {
                            setAccuracy(null);
                            onChange({
                                latitude: normalizeCoordinate(
                                    event.target.value,
                                ),
                                longitude,
                            });
                        }}
                        placeholder="-6.2000000"
                        autoComplete="off"
                        className="h-10"
                        aria-invalid={errors.latitude ? true : undefined}
                    />
                    <InputError message={errors.latitude} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="customer-longitude">Longitude</Label>
                    <Input
                        id="customer-longitude"
                        inputMode={COORDINATE_INPUT_MODE}
                        value={longitude}
                        onChange={(event) => {
                            setAccuracy(null);
                            onChange({
                                latitude,
                                longitude: normalizeCoordinate(
                                    event.target.value,
                                ),
                            });
                        }}
                        placeholder="106.8166667"
                        autoComplete="off"
                        className="h-10"
                        aria-invalid={errors.longitude ? true : undefined}
                    />
                    <InputError message={errors.longitude} />
                </div>
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button
                    type="button"
                    variant="outline"
                    className="h-10 w-full sm:w-auto"
                    onClick={useMyLocation}
                    disabled={!geolocation.isSupported || geolocation.locating}
                >
                    {geolocation.locating ? <Spinner /> : <LocateFixed />}
                    {geolocation.locating
                        ? 'Membaca lokasi…'
                        : 'Pakai lokasi saya'}
                </Button>
                {accuracy !== null && message === null ? (
                    <p className="text-sm text-muted-foreground">
                        Akurasi ±{formatNumber(Math.round(accuracy))} m
                    </p>
                ) : null}
            </div>
            {message ? (
                <p
                    className={
                        geolocation.error
                            ? 'text-sm text-destructive dark:text-destructive-foreground'
                            : 'text-sm text-muted-foreground'
                    }
                    role="status"
                >
                    {message}
                </p>
            ) : null}
        </div>
    );
}
