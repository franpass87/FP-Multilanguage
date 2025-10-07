/**
 * Action buttons handler
 * @since 0.3.2
 */

import { performAction } from './api-client.js';
import { setFeedback, renderProviderResult, handleCleanupResponse, handleQueueResponse } from './diagnostics.js';

/**
 * Inizializza gli action buttons
 */
export const initActionButtons = (feedback, providerResult) => {
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