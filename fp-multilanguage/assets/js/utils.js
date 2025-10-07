/**
 * Utility functions
 * @since 0.3.2
 */

export const escapeRegExp = (value) => value.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');

export const toReplacementValue = (value) => {
    if (Number.isFinite(value)) {
        return String(value);
    }

    if (typeof value === 'string') {
        return value;
    }

    if (value == null) {
        return '';
    }

    const parsed = Number(value);

    if (Number.isFinite(parsed)) {
        return String(parsed);
    }

    return String(value);
};