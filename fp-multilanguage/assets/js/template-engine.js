/**
 * Template Engine per sostituzioni dinamiche
 * @since 0.3.2
 */

import { escapeRegExp, toReplacementValue } from './utils.js';

/**
 * Riempie un template con i valori forniti
 */
export const fillTemplate = (template, replacements) => {
    if (!template) {
        return '';
    }

    let output = template;

    Object.keys(replacements).forEach((key) => {
        const token = new RegExp(`{{\\s*${escapeRegExp(key)}\\s*}}`, 'g');
        output = output.replace(token, replacements[key]);
    });

    return output;
};

/**
 * Costruisce replacements da un oggetto summary
 */
export const buildSummaryReplacements = (summary, defaults = {}) => {
    const replacements = { ...defaults };

    if (!summary || typeof summary !== 'object') {
        return replacements;
    }

    Object.keys(summary).forEach((key) => {
        const rawValue = summary[key];
        let value = rawValue;

        if (!Number.isFinite(value)) {
            if (typeof rawValue === 'string') {
                const parsed = Number(rawValue);
                value = Number.isFinite(parsed) ? parsed : rawValue;
            } else if (rawValue == null) {
                value = '';
            }
        }

        replacements[key] = toReplacementValue(value);
    });

    return replacements;
};