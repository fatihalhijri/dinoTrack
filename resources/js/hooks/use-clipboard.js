// Credit: https://usehooks-ts.com/
import { useState } from 'react';

/** @typedef {string | null} CopiedValue */
/** @typedef {(text: string) => Promise<boolean>} CopyFn */
/** @typedef {[CopiedValue, CopyFn]} UseClipboardReturn */

/** @returns {UseClipboardReturn} */
export function useClipboard() {
    const [copiedText, setCopiedText] = useState(
        /** @type {CopiedValue} */ (null),
    );

    /** @type {CopyFn} */
    const copy = async (text) => {
        if (!navigator?.clipboard) {
            console.warn('Clipboard not supported');

            return false;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopiedText(text);

            return true;
        } catch (error) {
            console.warn('Copy failed', error);
            setCopiedText(null);

            return false;
        }
    };

    return [copiedText, copy];
}
