import { useSyncExternalStore } from 'react';

const MOBILE_BREAKPOINT = 768;

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

/** @param {(event: MediaQueryListEvent) => void} callback */
function mediaQueryListener(callback) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

/** @returns {boolean} */
function isSmallerThanBreakpoint() {
    return mql?.matches ?? false;
}

/** @returns {boolean} */
function getServerSnapshot() {
    return false;
}

/** @returns {boolean} */
export function useIsMobile() {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}
