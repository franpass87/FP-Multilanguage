/**
 * Toggle functionality per mostrare/nascondere elementi
 * @since 0.3.2
 */

/**
 * Inizializza i toggle basati su data-fpml-toggle-target
 */
export const initToggles = () => {
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