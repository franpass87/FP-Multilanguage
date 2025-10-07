/**
 * Admin scripts for FP Multilanguage - Compiled bundle
 * @since 0.3.2
 * 
 * Questo file è generato automaticamente.
 * Per modifiche, edita i file nella cartella js/ ed esegui build-js.sh
 */

(function() {
'use strict';


// Module: utils
const utils = (function() {
/**
 * Utility functions
 * @since 0.3.2
 */

const escapeRegExp = (value) => value.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');

const toReplacementValue = (value) => {
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
})();

// Module: template-engine
const template_engine = (function() {
/**
 * Template Engine per sostituzioni dinamiche
 * @since 0.3.2
 */


/**
 * Riempie un template con i valori forniti
 */
const fillTemplate = (template, replacements) => {
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
const buildSummaryReplacements = (summary, defaults = {}) => {
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
})();

// Module: toggle
const toggle = (function() {
/**
 * Toggle functionality per mostrare/nascondere elementi
 * @since 0.3.2
 */

/**
 * Inizializza i toggle basati su data-fpml-toggle-target
 */
const initToggles = () => {
    const toggles = document.querySelectorAll('[data-fpml-toggle-target]');
    
    toggles.forEach((toggle) => {
        const trigger = () => {
            const targetSelector = toggle.getAttribute('data-fpml-toggle-target');
            if (!targetSelector) {
                return;
            }

            targetSelector.split(',').forEach((selector) => {
                const element = document.querySelector(selector.trim());
                if (!element) {
                    return;
                }

                element.style.display = toggle.checked ? '' : 'none';
            });
        };

        toggle.addEventListener('change', trigger);
        trigger();
    });
};
})();

// Module: api-client
const api_client = (function() {
/**
 * API Client per chiamate REST
 * @since 0.3.2
 */

/**
 * Esegue una richiesta POST all'endpoint specificato
 */
const performAction = async (endpoint, nonce) => {
    if (!endpoint || typeof window.fetch !== 'function') {
        throw new Error('Endpoint non valido o fetch non disponibile');
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
        },
        credentials: 'same-origin',
        body: '{}',
    });

    const payload = await response.json();

    if (!response.ok || !payload || payload.success !== true) {
        const message = (payload && payload.message) || 
                       (payload && payload.data && payload.data.message) || 
                       'Operazione non riuscita.';
        throw new Error(message);
    }

    return payload;
};
})();

// Module: diagnostics
const diagnostics = (function() {
/**
 * Diagnostics UI management
 * @since 0.3.2
 */


/**
 * Imposta un messaggio di feedback
 */
const setFeedback = (feedback, message, type) => {
    if (!feedback) {
        return;
    }
    feedback.textContent = message;
    feedback.className = 'fpml-diagnostics-feedback' + (type ? ' is-' + type : '');
};

/**
 * Renderizza i risultati del test del provider
 */
const renderProviderResult = (providerResult, data) => {
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
const handleCleanupResponse = (button, payload, template, defaultMessage) => {
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
const handleQueueResponse = (button, payload, action) => {
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
})();

// Module: action-buttons
const action_buttons = (function() {
/**
 * Action buttons handler
 * @since 0.3.2
 */


/**
 * Inizializza gli action buttons
 */
const initActionButtons = (feedback, providerResult) => {
    const actionButtons = document.querySelectorAll('[data-fpml-action]');

    if (!actionButtons.length || typeof window.fetch !== 'function') {
        return;
    }

    actionButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const endpoint = button.getAttribute('data-endpoint');
            const nonce = button.getAttribute('data-nonce') || '';
            const action = button.getAttribute('data-fpml-action');

            if (!endpoint) {
                return;
            }

            button.disabled = true;
            setFeedback(feedback, button.getAttribute('data-working-message') || 'Richiesta in corso…', 'info');

            try {
                const payload = await performAction(endpoint, nonce);

                if (action === 'test-provider') {
                    renderProviderResult(providerResult, payload);
                }

                let message = button.getAttribute('data-success-message') || 'Operazione completata.';

                if (action === 'cleanup') {
                    const template = button.getAttribute('data-success-template') || message;
                    message = handleCleanupResponse(button, payload, template, message);
                }

                if ((action === 'run-queue' || action === 'reindex') && payload.summary) {
                    const queueMessage = handleQueueResponse(button, payload, action);
                    if (queueMessage) {
                        message = queueMessage;
                    }
                }

                setFeedback(feedback, message, 'success');
            } catch (error) {
                setFeedback(feedback, error && error.message ? error.message : 'Errore di rete imprevisto.', 'error');
                if (providerResult && action === 'test-provider') {
                    providerResult.innerHTML = '';
                }
            } finally {
                button.disabled = false;
            }
        });
    });
};
})();

// Inizializzazione
(function() {
    toggle.initToggles();
    
    const feedback = document.querySelector('#fpml-diagnostics-feedback');
    const providerResult = document.querySelector('[data-fpml-provider-result]');
    
    action_buttons.initActionButtons(feedback, providerResult);
})();

})();
