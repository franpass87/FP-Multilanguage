/**
 * Admin scripts for FP Multilanguage - Main entry point
 * @since 0.3.2
 * 
 * Questo file importa e inizializza tutti i moduli.
 */

import { initToggles } from './js/toggle.js';
import { initActionButtons } from './js/action-buttons.js';

(function () {
    // Inizializza i toggle
    initToggles();

    // Inizializza gli action buttons
    const feedback = document.querySelector('#fpml-diagnostics-feedback');
    const providerResult = document.querySelector('[data-fpml-provider-result]');
    
    initActionButtons(feedback, providerResult);
})();