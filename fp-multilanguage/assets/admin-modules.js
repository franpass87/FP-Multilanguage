/**
 * Admin scripts for FP Multilanguage - ES6 Modules Entry Point
 * @since 0.3.2
 * 
 * Questo file è l'entry point per la modalità sviluppo con moduli ES6.
 * Carica i moduli dalla cartella js/ e inizializza l'applicazione.
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