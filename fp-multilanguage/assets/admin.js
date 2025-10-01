/*
 * Admin scripts for FP Multilanguage.
 * @since 0.2.0
 */
(function () {
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

    const actionButtons = document.querySelectorAll('[data-fpml-action]');
    const feedback = document.querySelector('#fpml-diagnostics-feedback');
    const providerResult = document.querySelector('[data-fpml-provider-result]');

    if (!actionButtons.length || typeof window.fetch !== 'function') {
        return;
    }

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

    const setFeedback = (message, type) => {
        if (!feedback) {
            return;
        }
        feedback.textContent = message;
        feedback.className = 'fpml-diagnostics-feedback' + (type ? ' is-' + type : '');
    };

    const renderProviderResult = (data) => {
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

    actionButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const endpoint = button.getAttribute('data-endpoint');
            const nonce = button.getAttribute('data-nonce') || '';
            const action = button.getAttribute('data-fpml-action');

            if (!endpoint) {
                return;
            }

            button.disabled = true;
            setFeedback(button.getAttribute('data-working-message') || 'Richiesta in corso…', 'info');

            try {
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
                    const message = (payload && payload.message) || (payload && payload.data && payload.data.message) || 'Operazione non riuscita.';
                    setFeedback(message, 'error');
                    if (providerResult && action === 'test-provider') {
                        providerResult.innerHTML = '';
                    }
                    return;
                }

                if (action === 'test-provider') {
                    renderProviderResult(payload);
                }

                let message = button.getAttribute('data-success-message') || 'Operazione completata.';

                if (action === 'cleanup') {
                    const deleted = payload && typeof payload.deleted !== 'undefined' ? Number(payload.deleted) : 0;
                    const days = payload && typeof payload.days !== 'undefined' ? Number(payload.days) : 0;
                    const states = payload && payload.states
                        ? (Array.isArray(payload.states) ? payload.states.join(', ') : String(payload.states))
                        : '';
                    const template = button.getAttribute('data-success-template') || message;
                    const replacements = {
                        deleted: toReplacementValue(Number.isFinite(deleted) ? deleted : 0),
                        days: toReplacementValue(Number.isFinite(days) ? days : 0),
                        states: toReplacementValue(states),
                    };
                    const filled = fillTemplate(template, replacements);

                    if (filled) {
                        message = filled;
                    }
                }

                if ((action === 'run-queue' || action === 'reindex') && payload.summary) {
                    const template = button.getAttribute('data-success-template');

                    if (template) {
                        const defaults =
                            action === 'run-queue'
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
                        const filled = fillTemplate(template, replacements);

                        if (filled) {
                            message = filled;
                        }
                    }
                }

                setFeedback(message, 'success');
            } catch (error) {
                setFeedback(error && error.message ? error.message : 'Errore di rete imprevisto.', 'error');
                if (providerResult && action === 'test-provider') {
                    providerResult.innerHTML = '';
                }
            } finally {
                button.disabled = false;
            }
        });
    });
})();
