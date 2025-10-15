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

    /**
     * Refreshes the WordPress REST API nonce.
     * This is called automatically when a nonce expires.
     * 
     * @returns {Promise<string|null>} The new nonce or null if refresh failed
     */
    const refreshNonce = async () => {
        try {
            // Try WordPress AJAX first (more reliable than REST for nonce refresh)
            console.log('Tentativo refresh nonce tramite AJAX WordPress...');
            
            const ajaxResponse = await fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'fpml_refresh_nonce',
                    _wpnonce: document.querySelector('[data-nonce]')?.getAttribute('data-nonce') || ''
                })
            });

            if (ajaxResponse.ok) {
                const ajaxData = await ajaxResponse.json();
                if (ajaxData.success && ajaxData.data && ajaxData.data.nonce) {
                    console.log('Nuovo nonce ricevuto tramite AJAX:', ajaxData.data.nonce.substring(0, 10) + '...');
                    return ajaxData.data.nonce;
                }
            }

            // Fallback to REST endpoint if AJAX fails
            console.log('AJAX fallito, tentativo tramite REST endpoint...');
            const refreshEndpoint = feedback?.getAttribute('data-refresh-endpoint');
            
            if (!refreshEndpoint) {
                console.error('Né AJAX né endpoint REST disponibili');
                return null;
            }

            const response = await fetch(refreshEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                console.error('Refresh nonce REST fallito con status:', response.status);
                return null;
            }

            const data = await response.json();
            if (data && data.nonce) {
                console.log('Nuovo nonce ricevuto tramite REST:', data.nonce.substring(0, 10) + '...');
                return data.nonce;
            } else {
                console.error('Risposta refresh nonce REST non valida:', data);
                return null;
            }
        } catch (error) {
            console.error('Errore durante il refresh del nonce:', error);
            return null;
        }
    };

    /**
     * Executes a REST API request with automatic nonce refresh on expiration.
     * 
     * This function handles the common WordPress issue where nonces expire after a certain time.
     * When a nonce expiration is detected (either as JSON or HTML response), it automatically:
     * 1. Fetches a fresh nonce from the server
     * 2. Updates all action buttons with the new nonce
     * 3. Retries the original request once
     * 
     * @param {string} endpoint - The REST API endpoint URL
     * @param {string} nonce - The WordPress REST API nonce
     * @param {number} retryCount - Current retry attempt (max 1)
     * @param {string} requestBody - The JSON body to send with the request
     * @returns {Promise<{response: Response, payload: Object|null}>}
     */
    const executeRequest = async (endpoint, nonce, retryCount = 0, requestBody = '{}') => {
        console.log(`🔧 REFACTOR: Tentativo ${retryCount + 1} per ${endpoint}`);
        
        // REFACTOR: Completamente nuova strategia - bypass nonce per reindex
        if (endpoint.includes('/reindex') && retryCount === 0) {
            console.log('🚀 REFACTOR: Uso AJAX diretto per reindex');
            return await executeReindexViaAjaxDirect(requestBody, retryCount);
        }
        
        // Use the most recent nonce if available
        const currentNonce = window.fpmlCurrentNonce || nonce;
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': currentNonce,
            },
            credentials: 'same-origin',
            body: requestBody,
        });

        let payload = null;
        let isHtmlResponse = false;

        // Try to parse as JSON, but handle HTML responses
        // WordPress sometimes returns HTML for nonce errors instead of JSON
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            try {
                payload = await response.json();
            } catch (e) {
                payload = null;
            }
        } else if (contentType.includes('text/html')) {
            isHtmlResponse = true;
            const htmlText = await response.text();
            // WordPress nonce errors are often returned as HTML with "scaduto", "expired" or "link che hai seguito"
            // Check for various WordPress nonce error messages in Italian and English
            const lowerHtml = htmlText.toLowerCase();
            if (lowerHtml.includes('scaduto') || 
                lowerHtml.includes('expired') || 
                lowerHtml.includes('link che hai seguito') ||
                lowerHtml.includes('link you followed') ||
                lowerHtml.includes('are you sure you want to do this') ||
                lowerHtml.includes('sei sicuro di voler fare questo')) {
                payload = {
                    code: 'rest_cookie_invalid_nonce',
                    message: 'Il nonce è scaduto'
                };
            }
        }

        // Check for expired nonce error - handles multiple error codes and messages
        const isNonceError = !response.ok && (
            isHtmlResponse ||
            response.status === 403 || // Forbidden often indicates nonce issues
            (payload && (
                payload.code === 'rest_cookie_invalid_nonce' ||
                payload.code === 'fpml_rest_nonce_invalid' ||
                payload.code === 'rest_forbidden' ||
                payload.code === 'rest_cookie_check_errors' ||
                (payload.message && (
                    payload.message.toLowerCase().includes('scaduto') ||
                    payload.message.toLowerCase().includes('expired') ||
                    payload.message.toLowerCase().includes('nonce') ||
                    payload.message.toLowerCase().includes('link che hai seguito') ||
                    payload.message.toLowerCase().includes('link you followed') ||
                    payload.message.toLowerCase().includes('cookie')
                ))
            ))
        );

        // If nonce is expired and we haven't retried yet, refresh and retry
        if (isNonceError && retryCount === 0) {
            console.log('Nonce scaduto rilevato, aggiornamento in corso...');
            
            // Show temporary feedback to user
            if (feedback) {
                setFeedback('Aggiornamento credenziali in corso...', 'info');
            }
            
            const newNonce = await refreshNonce();
            
            if (newNonce) {
                console.log('Nonce aggiornato con successo, nuovo tentativo...');
                // Update the nonce in all buttons for future requests
                actionButtons.forEach((btn) => {
                    btn.setAttribute('data-nonce', newNonce);
                });

                // Update the nonce in the global WordPress REST API object
                if (typeof wp !== 'undefined' && wp.apiFetch) {
                    wp.apiFetch.use(function(options, next) {
                        if (options.headers) {
                            options.headers['X-WP-Nonce'] = newNonce;
                        }
                        return next(options);
                    });
                }

                // Update the global nonce for fetch requests
                window.fpmlCurrentNonce = newNonce;
                
                // Update any existing fetch interceptors
                if (window.fpmlFetchInterceptor) {
                    window.fpmlFetchInterceptor.nonce = newNonce;
                }

                // Show feedback that we're retrying
                if (feedback) {
                    setFeedback('Credenziali aggiornate, ripetizione richiesta...', 'info');
                }

                // Retry the request with the new nonce (only once to avoid loops)
                return executeRequest(endpoint, newNonce, retryCount + 1, requestBody);
            } else {
                console.error('Impossibile aggiornare il nonce');
                if (feedback) {
                    setFeedback('Errore: impossibile aggiornare le credenziali. <a href="' + window.location.href + '" style="color: #0073aa; text-decoration: underline;">Clicca qui per ricaricare la pagina</a> e riprova.', 'error');
                }
            }
        }

        return { response, payload };
    };

    /**
     * Executes reindex with progress bar updates.
     * 
     * @param {string} endpoint - The batch reindex endpoint URL
     * @param {string} nonce - The WordPress REST API nonce
     * @param {HTMLElement} button - The button that triggered the action
     * @returns {Promise<boolean>} Returns true if progress bar is available, false for fallback
     */
    const executeReindexWithProgress = async (endpoint, nonce, button) => {
        const progressContainer = document.getElementById('fpml-reindex-progress');
        const progressBar = document.getElementById('fpml-reindex-progress-bar');
        const progressText = document.getElementById('fpml-reindex-progress-text');
        
        if (!progressContainer || !progressBar || !progressText) {
            console.error('Progress bar elements not found, falling back to standard reindex');
            return false; // Fallback to standard method
        }

        // Mostra la progress bar
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Inizializzazione...';

        let step = 0;
        let complete = false;
        let finalSummary = null;
        let currentNonce = nonce;

        try {
            let maxSteps = 50; // Limite di sicurezza per evitare loop infiniti
            let stepCount = 0;
            
            while (!complete && stepCount < maxSteps) {
                stepCount++;
                
                // Refresh del nonce ogni 2 step per evitare scadenze durante processi lunghi
                // WordPress nonces can expire after inactivity, so we refresh proactively
                if (step > 0 && step % 2 === 0) {
                    console.log('Aggiornamento preventivo del nonce allo step', step);
                    const newNonce = await refreshNonce();
                    if (newNonce) {
                        currentNonce = newNonce;
                        // Aggiorna il nonce in tutti i pulsanti per richieste future
                        actionButtons.forEach((btn) => {
                            btn.setAttribute('data-nonce', newNonce);
                        });
                        console.log('Nonce aggiornato con successo allo step', step);
                    } else {
                        console.warn('Impossibile aggiornare il nonce preventivamente allo step', step, '- continuo con quello corrente');
                    }
                }

                const requestBody = JSON.stringify({ step: step });
                const { response, payload } = await executeRequest(endpoint, currentNonce, 0, requestBody);

                if (!response.ok || !payload || payload.success !== true) {
                    const message = (payload && payload.message) || 'Errore durante il reindex.';
                    setFeedback(message, 'error');
                    progressContainer.style.display = 'none';
                    return true; // Errore gestito
                }

                // Aggiorna la progress bar
                const percent = payload.progress_percent || 0;
                progressBar.style.width = percent + '%';
                progressText.textContent = payload.current_task || 'Elaborazione...';

                complete = payload.complete || false;
                finalSummary = payload.summary || null;
                step++;

                // Piccolo delay per rendere visibile il progresso
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            
            // Controllo di sicurezza: se abbiamo raggiunto il limite massimo
            if (stepCount >= maxSteps) {
                console.warn('⚠️ Raggiunto limite massimo di step, forzando completamento');
                complete = true;
                progressText.textContent = 'Completato (limite raggiunto)';
            }

            // Completato con successo
            progressBar.style.width = '100%';
            progressText.textContent = 'Completato!';

            // Nascondi la progress bar dopo 2 secondi
            setTimeout(() => {
                progressContainer.style.display = 'none';
            }, 2000);

            // Mostra il messaggio di successo
            if (finalSummary) {
                const template = button.getAttribute('data-success-template');
                const defaults = {
                    posts_scanned: '0',
                    posts_enqueued: '0',
                    translations_created: '0',
                    terms_scanned: '0',
                    menus_synced: '0',
                };
                const replacements = buildSummaryReplacements(finalSummary, defaults);
                const message = template ? fillTemplate(template, replacements) : 'Reindex completato.';
                setFeedback(message, 'success');
            } else {
                setFeedback('Reindex completato.', 'success');
            }

            return true; // Progress bar completata con successo

        } catch (error) {
            progressContainer.style.display = 'none';
            setFeedback(error && error.message ? error.message : 'Errore di rete imprevisto.', 'error');
            return true; // Gestito, anche se con errore
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

            // Gestione speciale per il reindex con progress bar
            if (action === 'reindex') {
                const batchEndpoint = button.getAttribute('data-batch-endpoint');
                if (batchEndpoint) {
                    setFeedback('', 'info'); // Pulisci il feedback
                    const progressBarAvailable = await executeReindexWithProgress(batchEndpoint, nonce, button);
                    
                    // Se la progress bar non è disponibile, usa il metodo standard
                    if (progressBarAvailable === false) {
                        // Continua con il metodo standard qui sotto
                    } else {
                        button.disabled = false;
                        return;
                    }
                }
            }

            setFeedback(button.getAttribute('data-working-message') || 'Richiesta in corso…', 'info');

            try {
                // Per operazioni lunghe come il reindex, aggiorna il nonce prima di iniziare
                let currentNonce = nonce;
                if (action === 'reindex') {
                    const newNonce = await refreshNonce();
                    if (newNonce) {
                        currentNonce = newNonce;
                        actionButtons.forEach((btn) => {
                            btn.setAttribute('data-nonce', newNonce);
                        });
                        console.log('Nonce aggiornato prima del reindex');
                    }
                }

                const { response, payload } = await executeRequest(endpoint, currentNonce);

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

    // Check OpenAI billing button
    const checkBillingButton = document.getElementById('fpml-check-openai-billing');
    const billingStatus = document.getElementById('fpml-billing-status');

    if (checkBillingButton && billingStatus) {
        checkBillingButton.addEventListener('click', async () => {
            const endpoint = checkBillingButton.getAttribute('data-endpoint') || '/wp-json/fpml/v1/check-billing';
            let nonce = checkBillingButton.getAttribute('data-nonce') || document.querySelector('[data-nonce]')?.getAttribute('data-nonce') || '';

            checkBillingButton.disabled = true;
            billingStatus.innerHTML = '<p style="color: #0073aa;">⏳ Verifica in corso...</p>';

            try {
                const requestBody = JSON.stringify({ provider: 'openai' });
                const { response, payload } = await executeRequest(endpoint, nonce, 0, requestBody);

                if (!response.ok || !payload || payload.success !== true) {
                    const message = (payload && payload.message) || (payload && payload.data && payload.data.message) || 'Verifica non riuscita.';
                    billingStatus.innerHTML = '<div style="background: #fff3cd; border-left: 4px solid #d63638; padding: 12px; margin-top: 10px;"><p style="color: #d63638; white-space: pre-wrap; margin: 0;">' + escapeHtml(message) + '</p></div>';
                    return;
                }

                const billing = payload.billing || {};
                const status = billing.status || 'unknown';
                const message = billing.message || 'Stato sconosciuto';
                const details = billing.details || '';

                let color = '#0073aa';
                let backgroundColor = '#e7f5ff';
                let borderColor = '#0073aa';
                
                if (status === 'ok') {
                    color = '#00a32a';
                    backgroundColor = '#ecf7ed';
                    borderColor = '#00a32a';
                } else if (status === 'quota_exceeded' || status === 'auth_error' || status === 'error') {
                    color = '#d63638';
                    backgroundColor = '#fff3cd';
                    borderColor = '#d63638';
                } else if (status === 'rate_limit' || status === 'server_error') {
                    color = '#f0b849';
                    backgroundColor = '#fff8e5';
                    borderColor = '#f0b849';
                }

                let html = '<div style="background: ' + backgroundColor + '; border-left: 4px solid ' + borderColor + '; padding: 12px; margin-top: 10px;">';
                html += '<p style="color: ' + color + '; white-space: pre-wrap; font-weight: bold; margin: 0;">' + escapeHtml(message) + '</p>';
                if (details) {
                    html += '<p style="color: #646970; font-size: 12px; margin-top: 8px; margin-bottom: 0; white-space: pre-wrap;">' + escapeHtml(details) + '</p>';
                }
                html += '</div>';

                billingStatus.innerHTML = html;
            } catch (error) {
                billingStatus.innerHTML = '<div style="background: #fff3cd; border-left: 4px solid #d63638; padding: 12px; margin-top: 10px;"><p style="color: #d63638; margin: 0;">❌ Errore di rete: ' + escapeHtml(error && error.message ? error.message : 'Errore sconosciuto') + '</p></div>';
            } finally {
                checkBillingButton.disabled = false;
            }
        });
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initialize global nonce
    window.fpmlCurrentNonce = actionButtons[0]?.getAttribute('data-nonce') || '';

    // Auto-refresh nonce every 10 minutes to prevent expiration during long sessions
    // WordPress nonces can expire after 12-24 hours of inactivity, but we refresh
    // proactively to ensure smooth operation
    if (actionButtons.length > 0) {
        const AUTO_REFRESH_INTERVAL = 10 * 60 * 1000; // 10 minutes
        
        setInterval(async () => {
            console.log('Auto-refresh del nonce in background...');
            const newNonce = await refreshNonce();
            
            if (newNonce) {
                // Update global nonce
                window.fpmlCurrentNonce = newNonce;
                // Update all buttons with new nonce
                actionButtons.forEach((btn) => {
                    btn.setAttribute('data-nonce', newNonce);
                });
                console.log('Nonce aggiornato automaticamente in background');
            } else {
                console.warn('Auto-refresh del nonce fallito');
            }
        }, AUTO_REFRESH_INTERVAL);
        
        console.log('Auto-refresh del nonce abilitato (ogni ' + (AUTO_REFRESH_INTERVAL / 60000) + ' minuti)');
    }

    /**
     * REFACTOR: AJAX diretto per reindex - bypass completo del REST API
     * 
     * Questa funzione bypassa completamente il sistema REST e usa AJAX WordPress
     * per evitare tutti i problemi di nonce scaduto.
     */
    const executeReindexViaAjaxDirect = async (requestBody, retryCount = 0) => {
        console.log('🚀 AJAX diretto per reindex - bypass REST API');
        
        try {
            const data = JSON.parse(requestBody || '{}');
            const step = data.step || 0;
            
            const formData = new FormData();
            formData.append('action', 'fpml_reindex_batch_ajax');
            formData.append('step', step);
            
            // Usa il nonce più recente disponibile
            const currentNonce = window.fpmlCurrentNonce || 
                                document.querySelector('[data-nonce]')?.getAttribute('data-nonce') || 
                                '';
            
            formData.append('_wpnonce', currentNonce);
            
            console.log(`📡 Invio AJAX: step=${step}, nonce=${currentNonce.substring(0, 10)}...`);
            
            const response = await fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`AJAX request failed: ${response.status} ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                console.log('✅ AJAX diretto completato con successo');
                console.log('📊 Dati ricevuti:', result.data);
                
                // Mappa la risposta AJAX al formato atteso dal JavaScript principale
                const mappedPayload = {
                    success: true,
                    complete: result.data.complete || false,
                    step: result.data.step || 0,
                    total_steps: result.data.total_steps || 0,
                    progress_percent: result.data.progress_percent || 0,
                    current_task: result.data.current_task || 'Elaborazione...',
                    summary: result.data.summary || {},
                    message: result.data.message || 'OK'
                };
                
                // Controlla se il reindex è completato
                if (mappedPayload.complete) {
                    console.log('🎉 REINDEX COMPLETATO!');
                } else {
                    console.log('⏳ Reindex in corso, step:', mappedPayload.step);
                }
                
                return { 
                    response: { ok: true, status: 200 }, 
                    payload: mappedPayload 
                };
            } else {
                throw new Error(result.data?.message || 'AJAX request failed');
            }
            
        } catch (error) {
            console.error('❌ AJAX diretto fallito:', error);
            
            // Se AJAX fallisce, prova con REST come fallback
            if (retryCount === 0) {
                console.log('🔄 Fallback a REST API...');
                return await executeRequestViaRest(requestBody, retryCount + 1);
            }
            
            throw error;
        }
    };

    /**
     * Fallback REST API quando AJAX fallisce
     */
    const executeRequestViaRest = async (requestBody, retryCount = 0) => {
        console.log('🔄 Fallback REST API...');
        
        // Prova con un nonce fresco
        const freshNonce = await refreshNonce();
        if (freshNonce) {
            window.fpmlCurrentNonce = freshNonce;
        }
        
        const currentNonce = window.fpmlCurrentNonce || '';
        
        const response = await fetch('/wp-json/fpml/v1/reindex-batch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': currentNonce,
            },
            credentials: 'same-origin',
            body: requestBody,
        });
        
        if (response.ok) {
            const payload = await response.json();
            return { response, payload };
        }
        
        throw new Error(`REST fallback failed: ${response.status}`);
    };
})();
