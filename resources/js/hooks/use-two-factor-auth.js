import { useHttp } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import { qrCode, recoveryCodes, secretKey } from '@/routes/two-factor';

/**
 * @typedef {object} UseTwoFactorAuthReturn
 * @property {string | null} qrCodeSvg
 * @property {string | null} manualSetupKey
 * @property {string[]} recoveryCodesList
 * @property {boolean} hasSetupData
 * @property {string[]} errors
 * @property {() => void} clearErrors
 * @property {() => void} clearSetupData
 * @property {() => void} clearTwoFactorAuthData
 * @property {() => Promise<void>} fetchQrCode
 * @property {() => Promise<void>} fetchSetupKey
 * @property {() => Promise<void>} fetchSetupData
 * @property {() => Promise<void>} fetchRecoveryCodes
 */

export const OTP_MAX_LENGTH = 6;

/** @returns {UseTwoFactorAuthReturn} */
export const useTwoFactorAuth = () => {
    const { submit } = useHttp();

    const [qrCodeSvg, setQrCodeSvg] = useState(
        /** @type {string | null} */ (null),
    );
    const [manualSetupKey, setManualSetupKey] = useState(
        /** @type {string | null} */ (null),
    );
    const [recoveryCodesList, setRecoveryCodesList] = useState(
        /** @type {string[]} */ ([]),
    );
    const [errors, setErrors] = useState(/** @type {string[]} */ ([]));

    const hasSetupData = qrCodeSvg !== null && manualSetupKey !== null;

    const clearErrors = useCallback(() => {
        setErrors([]);
    }, []);

    const clearSetupData = useCallback(() => {
        setManualSetupKey(null);
        setQrCodeSvg(null);
        setErrors([]);
    }, []);

    const clearTwoFactorAuthData = useCallback(() => {
        setManualSetupKey(null);
        setQrCodeSvg(null);
        setErrors([]);
        setRecoveryCodesList([]);
    }, []);

    const fetchQrCode = useCallback(async () => {
        try {
            const { svg } = /** @type {{ svg: string; url: string }} */ (
                await submit(qrCode())
            );

            setQrCodeSvg(svg);
        } catch {
            setErrors((prev) => [...prev, 'Gagal memuat kode QR']);
            setQrCodeSvg(null);
        }
    }, [submit]);

    const fetchSetupKey = useCallback(async () => {
        try {
            const { secretKey: key } = /** @type {{ secretKey: string }} */ (
                await submit(secretKey())
            );

            setManualSetupKey(key);
        } catch {
            setErrors((prev) => [...prev, 'Gagal memuat kunci setup']);
            setManualSetupKey(null);
        }
    }, [submit]);

    const fetchRecoveryCodes = useCallback(async () => {
        try {
            setErrors([]);
            const codes = /** @type {string[]} */ (
                await submit(recoveryCodes())
            );
            setRecoveryCodesList(codes);
        } catch {
            setErrors((prev) => [...prev, 'Gagal memuat kode pemulihan']);
            setRecoveryCodesList([]);
        }
    }, [submit]);

    const fetchSetupData = useCallback(async () => {
        try {
            setErrors([]);
            await Promise.all([fetchQrCode(), fetchSetupKey()]);
        } catch {
            setQrCodeSvg(null);
            setManualSetupKey(null);
        }
    }, [fetchQrCode, fetchSetupKey]);

    return {
        qrCodeSvg,
        manualSetupKey,
        recoveryCodesList,
        hasSetupData,
        errors,
        clearErrors,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchQrCode,
        fetchSetupKey,
        fetchSetupData,
        fetchRecoveryCodes,
    };
};
