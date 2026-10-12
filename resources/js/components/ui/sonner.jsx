import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner } from 'sonner';
/** @import { ToasterProps } from 'sonner' */
/** @import * as React from 'react' */

/**
 * @param {ToasterProps} props
 */
function Toaster({ ...props }) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                /** @type {React.CSSProperties} */ ({
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                })
            }
            {...props}
        />
    );
}

export { Toaster };
