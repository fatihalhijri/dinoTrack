/**
 * Format tampilan Bahasa Indonesia. Semua halaman memformat uang dan tanggal lewat file ini.
 *
 * Tanggal dari backend: kolom date = 'YYYY-MM-DD' (tanpa zona), timestamp = ISO 8601
 * dengan offset. Tanggal saja dirakit sebagai tanggal lokal agar tidak bergeser hari
 * (new Date('2026-10-06') dibaca UTC), timestamp ditampilkan dalam zona Asia/Jakarta.
 */

const TIME_ZONE = 'Asia/Jakarta';
const EMPTY = '—';

const MONTHS_SHORT = /** @type {const} */ ([
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
]);

const MONTHS_LONG = /** @type {const} */ ([
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
]);

const DATE_ONLY = /^(\d{4})-(\d{2})-(\d{2})$/;
const YEAR_MONTH = /^(\d{4})-(\d{2})$/;

const numberFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
});

const compactNumberFormatter = new Intl.NumberFormat('id-ID', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const jakartaParts = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

/**
 * @typedef {object} DateParts
 * @property {number} year
 * @property {number} month
 * @property {number} day
 * @property {number} hour
 * @property {number} minute
 */

/**
 * `1234567` → `1.234.567`
 *
 * @param {number} value
 * @returns {string}
 */
export function formatNumber(value) {
    return numberFormatter.format(value);
}

/**
 * Integer rupiah → `Rp150.000` (negatif: `-Rp150.000`).
 *
 * @param {number} amount
 * @returns {string}
 */
export function formatRupiah(amount) {
    const formatted = `Rp${numberFormatter.format(Math.abs(amount))}`;

    return amount < 0 ? `-${formatted}` : formatted;
}

/**
 * Integer rupiah ringkas untuk sumbu grafik → `Rp250 rb`, `Rp1,5 jt`, `Rp2 M`.
 *
 * @param {number} amount
 * @returns {string}
 */
export function formatRupiahCompact(amount) {
    const formatted = `Rp${compactNumberFormatter.format(Math.abs(amount))}`;

    return amount < 0 ? `-${formatted}` : formatted;
}

/**
 * `'2026-10-06'` → Date lokal 6 Oktober 2026 00.00, atau null jika formatnya salah.
 *
 * @param {string} value
 * @returns {Date | null}
 */
export function parseDateOnly(value) {
    const match = DATE_ONLY.exec(value);

    if (match === null) {
        return null;
    }

    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

/**
 * @param {string} value
 * @returns {DateParts | null}
 */
function partsOf(value) {
    const dateOnly = parseDateOnly(value);

    if (dateOnly !== null) {
        return {
            year: dateOnly.getFullYear(),
            month: dateOnly.getMonth() + 1,
            day: dateOnly.getDate(),
            hour: 0,
            minute: 0,
        };
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    /** @type {Record<string, number>} */
    const parts = {};

    for (const part of jakartaParts.formatToParts(date)) {
        if (part.type !== 'literal') {
            parts[part.type] = Number(part.value);
        }
    }

    return {
        year: parts.year,
        month: parts.month,
        day: parts.day,
        hour: parts.hour,
        minute: parts.minute,
    };
}

/**
 * @param {number} month
 * @returns {string}
 */
function shortMonth(month) {
    return MONTHS_SHORT[month - 1] ?? '';
}

/**
 * @param {number} value
 * @returns {string}
 */
function pad(value) {
    return String(value).padStart(2, '0');
}

/**
 * `'2026-10-06'` atau timestamp → `6 Okt 2026`.
 *
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function formatDate(value) {
    const parts = value ? partsOf(value) : null;

    if (parts === null) {
        return value ?? EMPTY;
    }

    return `${parts.day} ${shortMonth(parts.month)} ${parts.year}`;
}

/**
 * Timestamp ISO → `6 Okt 2026 14.30` (WIB).
 *
 * @param {string | null | undefined} value
 * @returns {string}
 */
export function formatDateTime(value) {
    const parts = value ? partsOf(value) : null;

    if (parts === null) {
        return value ?? EMPTY;
    }

    return `${parts.day} ${shortMonth(parts.month)} ${parts.year} ${pad(parts.hour)}.${pad(parts.minute)}`;
}

/**
 * Nilai `<input type="date">` (`YYYY-MM-DD`) menurut tanggal WIB: timestamp ISO atau,
 * tanpa argumen, hari ini. Dipakai untuk default dan batas min/max input tanggal.
 *
 * @param {string} [value]
 * @returns {string}
 */
export function toDateInputValue(value = new Date().toISOString()) {
    const parts = partsOf(value);

    if (parts === null) {
        return '';
    }

    return `${parts.year}-${pad(parts.month)}-${pad(parts.day)}`;
}

/**
 * Periode tagihan → `6 Okt – 5 Nov 2026` (tahun ditulis dua kali jika berbeda).
 *
 * @param {string} start
 * @param {string} end
 * @returns {string}
 */
export function formatPeriod(start, end) {
    const from = partsOf(start);
    const to = partsOf(end);

    if (from === null || to === null) {
        return `${start} – ${end}`;
    }

    const fromLabel =
        from.year === to.year
            ? `${from.day} ${shortMonth(from.month)}`
            : `${from.day} ${shortMonth(from.month)} ${from.year}`;

    return `${fromLabel} – ${to.day} ${shortMonth(to.month)} ${to.year}`;
}

/**
 * Nama bulan: `10` → `Oktober`, `'2026-10'` → `Oktober 2026`.
 * `short: true` memakai singkatan (`Okt`, `Okt 2026`).
 *
 * @param {number | string} value
 * @param {{ short?: boolean }} [options]
 * @returns {string}
 */
export function formatMonth(value, options = {}) {
    const names = options.short ? MONTHS_SHORT : MONTHS_LONG;

    if (typeof value === 'number') {
        return names[value - 1] ?? String(value);
    }

    const match = YEAR_MONTH.exec(value);

    if (match === null) {
        return value;
    }

    return `${names[Number(match[2]) - 1] ?? match[2]} ${match[1]}`;
}

/**
 * Nomor tersimpan `6281234567890` → tampilan `0812-3456-7890`.
 *
 * @param {string | null | undefined} phone
 * @returns {string}
 */
export function formatPhone(phone) {
    if (!phone) {
        return EMPTY;
    }

    const local = phone.startsWith('62') ? `0${phone.slice(2)}` : phone;
    const groups = [local.slice(0, 4)];

    for (let index = 4; index < local.length; index += 4) {
        groups.push(local.slice(index, index + 4));
    }

    return groups.join('-');
}

/** Batas digit input Rupiah agar hasilnya tetap integer aman (di bawah 2^53). */
const MAX_RUPIAH_DIGITS = 15;

/**
 * Teks input Rupiah `'150.000'` / `'Rp150000'` → `150000`, atau null jika tanpa angka.
 *
 * @param {string} value
 * @returns {number | null}
 */
export function parseRupiahInput(value) {
    const digits = value.replace(/\D/g, '').slice(0, MAX_RUPIAH_DIGITS);

    return digits === '' ? null : Number(digits);
}
