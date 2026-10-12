import { useState } from 'react';

/**
 * @typedef {object} GeolocationResult
 * @property {number} latitude
 * @property {number} longitude
 * @property {number} accuracy Radius akurasi dalam meter.
 */

/**
 * @typedef {object} UseGeolocationReturn
 * @property {boolean} isSupported False jika browser tidak mendukung atau halaman tidak dibuka lewat HTTPS/localhost.
 * @property {string | null} unsupportedReason
 * @property {boolean} locating
 * @property {string | null} error
 * @property {(onSuccess: (result: GeolocationResult) => void) => void} locate
 */

const LOCATE_TIMEOUT_MS = 15000;

/** @returns {string | null} */
function unsupportedReasonOf() {
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

/**
 * @param {GeolocationPositionError} error
 * @returns {string}
 */
function errorMessageOf(error) {
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
 *
 * @returns {UseGeolocationReturn}
 */
export function useGeolocation() {
    const [unsupportedReason] = useState(unsupportedReasonOf);
    const [locating, setLocating] = useState(false);
    const [error, setError] = useState(/** @type {string | null} */ (null));

    /** @type {UseGeolocationReturn['locate']} */
    const locate = (onSuccess) => {
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
