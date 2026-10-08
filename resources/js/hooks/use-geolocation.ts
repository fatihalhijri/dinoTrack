import { useState } from 'react';

export type GeolocationResult = {
    latitude: number;
    longitude: number;
    /** Radius akurasi dalam meter. */
    accuracy: number;
};

export type UseGeolocationReturn = {
    /** False jika browser tidak mendukung atau halaman tidak dibuka lewat HTTPS/localhost. */
    isSupported: boolean;
    unsupportedReason: string | null;
    locating: boolean;
    error: string | null;
    locate: (onSuccess: (result: GeolocationResult) => void) => void;
};

const LOCATE_TIMEOUT_MS = 15000;

function unsupportedReasonOf(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (!window.isSecureContext) {
        return 'Lokasi hanya bisa dibaca jika aplikasi dibuka lewat HTTPS. Isi koordinat secara manual.';
    }

    if (!('geolocation' in navigator)) {
        return 'Browser ini tidak mendukung pembacaan lokasi. Isi koordinat secara manual.';
    }

    return null;
}

function errorMessageOf(error: GeolocationPositionError): string {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'Izin lokasi ditolak. Izinkan akses lokasi untuk situs ini di pengaturan browser, atau isi koordinat secara manual.';
        case error.POSITION_UNAVAILABLE:
            return 'Lokasi tidak tersedia. Pastikan GPS aktif, lalu coba lagi.';
        case error.TIMEOUT:
            return 'Lokasi belum didapat dalam 15 detik. Coba lagi di tempat terbuka.';
        default:
            return 'Lokasi tidak dapat dibaca. Isi koordinat secara manual.';
    }
}

/**
 * Membaca lokasi perangkat sekali (misalnya saat teknisi berada di rumah pelanggan).
 * Galat Geolocation API diterjemahkan ke pesan Bahasa Indonesia.
 */
export function useGeolocation(): UseGeolocationReturn {
    const [unsupportedReason] = useState(unsupportedReasonOf);
    const [locating, setLocating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const locate: UseGeolocationReturn['locate'] = (onSuccess) => {
        if (unsupportedReason !== null) {
            setError(unsupportedReason);

            return;
        }

        setLocating(true);
        setError(null);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                onSuccess({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy,
                });
            },
            (positionError) => {
                setLocating(false);
                setError(errorMessageOf(positionError));
            },
            {
                enableHighAccuracy: true,
                timeout: LOCATE_TIMEOUT_MS,
                maximumAge: 0,
            },
        );
    };

    return {
        isSupported: unsupportedReason === null,
        unsupportedReason,
        locating,
        error,
        locate,
    };
}
