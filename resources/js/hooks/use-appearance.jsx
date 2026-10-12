import { useSyncExternalStore } from 'react';

/** @typedef {'light' | 'dark'} ResolvedAppearance */
/** @typedef {ResolvedAppearance | 'system'} Appearance */

/**
 * @typedef {{
 *     readonly appearance: Appearance;
 *     readonly resolvedAppearance: ResolvedAppearance;
 *     readonly updateAppearance: (mode: Appearance) => void;
 * }} UseAppearanceReturn
 */

/** @type {Set<() => void>} */
const listeners = new Set();
/** @type {Appearance} */
let currentAppearance = 'system';

/** @returns {boolean} */
const prefersDark = () => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

/**
 * @param {string} name
 * @param {string} value
 * @param {number} [days]
 * @returns {void}
 */
const setCookie = (name, value, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

/** @returns {Appearance} */
const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return 'system';
    }

    return (
        /** @type {Appearance} */ (localStorage.getItem('appearance')) ||
        'system'
    );
};

/**
 * @param {Appearance} appearance
 * @returns {boolean}
 */
const isDarkMode = (appearance) => {
    return appearance === 'dark' || (appearance === 'system' && prefersDark());
};

/**
 * @param {Appearance} appearance
 * @returns {void}
 */
const applyTheme = (appearance) => {
    if (typeof document === 'undefined') {
        return;
    }

    const isDark = isDarkMode(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
};

/** @param {() => void} callback */
const subscribe = (callback) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

/** @returns {void} */
const notify = () => listeners.forEach((listener) => listener());

/** @returns {MediaQueryList | null} */
const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

/** @returns {void} */
const handleSystemThemeChange = () => applyTheme(currentAppearance);

/** @returns {void} */
export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    if (!localStorage.getItem('appearance')) {
        localStorage.setItem('appearance', 'system');
        setCookie('appearance', 'system');
    }

    currentAppearance = getStoredAppearance();
    applyTheme(currentAppearance);

    // Set up system theme change listener
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

/** @returns {UseAppearanceReturn} */
export function useAppearance() {
    /** @type {Appearance} */
    const appearance = useSyncExternalStore(
        subscribe,
        () => currentAppearance,
        () => 'system',
    );

    /** @type {ResolvedAppearance} */
    const resolvedAppearance = isDarkMode(appearance) ? 'dark' : 'light';

    /**
     * @param {Appearance} mode
     * @returns {void}
     */
    const updateAppearance = (mode) => {
        currentAppearance = mode;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', mode);

        // Store in cookie for SSR...
        setCookie('appearance', mode);

        applyTheme(mode);
        notify();
    };

    return { appearance, resolvedAppearance, updateAppearance };
}
