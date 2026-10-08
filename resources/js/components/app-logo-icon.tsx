import type { SVGAttributes } from 'react';

/**
 * Logo DinoTrack: huruf "D" dengan titik simpul jaringan di tengahnya.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6 5h8.5C21.4 5 26.5 9.7 26.5 16S21.4 27 14.5 27H6V5Zm5 5v12h3.4c3.8 0 6.6-2.5 6.6-6s-2.8-6-6.6-6H11Z"
            />
            <circle cx="15" cy="16" r="2.25" />
        </svg>
    );
}
