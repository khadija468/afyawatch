const integer = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 });
const decimal = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const twoDecimals = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const SMALL_NUMBER_LIMIT = 5;

export const fmtInt = (n) => (n === null || n === undefined ? 'no data' : integer.format(n));
export const fmt1 = (n) => (n === null || n === undefined ? 'no data' : decimal.format(n));
export const fmt2 = (n) => (n === null || n === undefined ? 'no data' : twoDecimals.format(n));
export const fmtPct = (n) => (n === null || n === undefined ? 'no data' : `${decimal.format(n)}%`);

export const isSmall = (n) => n !== null && n !== undefined && n > 0 && n < SMALL_NUMBER_LIMIT;

/** A count with a visible marker when it is a small number. */
export const fmtCount = (n) => (isSmall(n) ? `${fmtInt(n)}‡` : fmtInt(n));

export function fmtMonth(yyyyMm) {
    const [year, month] = yyyyMm.split('-').map(Number);
    return new Date(Date.UTC(year, month - 1, 1)).toLocaleDateString('en-GB', { month: 'short', year: 'numeric', timeZone: 'UTC' });
}

export function fmtDateTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
