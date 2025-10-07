/**
 * Diagnostics UI management
 * @since 0.3.2
 */

import { fillTemplate, buildSummaryReplacements } from './template-engine.js';
import { toReplacementValue } from './utils.js';

/**
 * Imposta un messaggio di feedback
 */
export const setFeedback = (feedback, message, type) => {
    if (!feedback) {
        return;
    }
    feedback.textContent = message;
    feedback.className = 'fpml-diagnostics-feedback' + (type ? ' is-' + type : '');
};

/**
 * Renderizza i risultati del test del provider
 */
export const renderProviderResult = (providerResult, data) => {
    if (!providerResult) {
        return;
    }

    providerResult.innerHTML = '';

    if (!data || !data.success) {
        return;
    }

    const list = document.createElement('dl');
    list.className = 'fpml-provider-result__metrics';

    const rows = [
        { label: 'Provider', value: data.provider || '-' },
        { label: 'Latenza (s)', value: data.elapsed != null ? Number(data.elapsed).toFixed(3) : '0.000' },
        { label: 'Caratteri', value: data.characters != null ? data.characters : 0 },
        { label: 'Costo stimato', value: data.estimated_cost != null ? Number(data.estimated_cost).toFixed(4) + ' €' : '0.0000 €' },
    ];

    rows.forEach((row) => {
        const dt = document.createElement('dt');
        dt.textContent = row.label;
        const dd = document.createElement('dd');
        dd.textContent = row.value;
        list.appendChild(dt);
        list.appendChild(dd);
    });

    providerResult.appendChild(list);

    if (data.sample) {
        const sampleTitle = document.createElement('h3');
        sampleTitle.className = 'fpml-provider-result__title';
        sampleTitle.textContent = 'Testo di partenza';
        const sample = document.createElement('pre');
        sample.className = 'fpml-provider-result__text';
        sample.textContent = data.sample;
        providerResult.appendChild(sampleTitle);
        providerResult.appendChild(sample);
    }

    if (data.translation) {
        const translationTitle = document.createElement('h3');
        translationTitle.className = 'fpml-provider-result__title';
        translationTitle.textContent = 'Traduzione';
        const translation = document.createElement('pre');
        translation.className = 'fpml-provider-result__text';
        translation.textContent = data.translation;
        providerResult.appendChild(translationTitle);
        providerResult.appendChild(translation);
    }
};

/**
 * Gestisce la risposta per l'azione cleanup
 */
export const handleCleanupResponse = (button, payload, template, defaultMessage) => {
    const deleted = payload && typeof payload.deleted !== 'undefined' ? Number(payload.deleted) : 0;
    const days = payload && typeof payload.days !== 'undefined' ? Number(payload.days) : 0;
    const states = payload && payload.states
        ? (Array.isArray(payload.states) ? payload.states.join(', ') : String(payload.states))
        : '';
    
    const replacements = {
        deleted: toReplacementValue(Number.isFinite(deleted) ? deleted : 0),
        days: toReplacementValue(Number.isFinite(days) ? days : 0),
        states: toReplacementValue(states),
    };
    
    const filled = fillTemplate(template, replacements);
    return filled || defaultMessage;
};

/**
 * Gestisce la risposta per azioni run-queue e reindex
 */
export const handleQueueResponse = (button, payload, action) => {
    const template = button.getAttribute('data-success-template');
    
    if (!template) {
        return null;
    }

    const defaults = action === 'run-queue'
        ? {
              claimed: '0',
              processed: '0',
              skipped: '0',
              errors: '0',
          }
        : {
              posts_scanned: '0',
              posts_enqueued: '0',
              translations_created: '0',
              terms_scanned: '0',
              menus_synced: '0',
          };
    
    const replacements = buildSummaryReplacements(payload.summary, defaults);
    return fillTemplate(template, replacements);
};