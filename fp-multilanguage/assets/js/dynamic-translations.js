(function (window, document) {
    'use strict';

    var config = window.fpMultilanguageDynamic || {};
    var manualStrings = config.manualStrings || {};
    var language = config.language || '';
    var canEdit = !!config.canEdit;
    var prompts = config.prompts || {};
    var editPrompt = typeof prompts.edit === 'string' && prompts.edit.length > 0
        ? prompts.edit
        : 'Traduzione manuale';

    function getManualValue(key) {
        if (manualStrings[key] && manualStrings[key][language]) {
            return manualStrings[key][language];
        }

        return null;
    }

    function applyManualStrings(root) {
        var elements = (root || document).querySelectorAll('[data-fp-translatable]');
        elements.forEach(function (element) {
            var key = element.getAttribute('data-fp-translatable');
            if (!key) {
                return;
            }

            var manualValue = getManualValue(key);
            if (manualValue !== null) {
                element.innerText = manualValue;
            }

            if (canEdit) {
                element.classList.add('fp-multilanguage-editable');
                element.addEventListener('dblclick', function () {
                    var current = manualValue !== null ? manualValue : element.innerText;
                    var value = window.prompt(editPrompt, current);
                    if (value === null) {
                        return;
                    }

                    saveManualString(key, value, function () {
                        manualStrings[key] = manualStrings[key] || {};
                        manualStrings[key][language] = value;
                        manualValue = value;
                        element.innerText = value;
                    });
                });
            }
        });
    }

    function saveManualString(key, value, callback) {
        if (!config.ajaxUrl) {
            return;
        }

        var xhr = new window.XMLHttpRequest();
        var params = new window.URLSearchParams();
        params.append('action', 'fp_multilanguage_save_string');
        params.append('nonce', config.nonce);
        params.append('key', key);
        params.append('language', language);
        params.append('value', value);

        xhr.open('POST', config.ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === window.XMLHttpRequest.DONE) {
                if (xhr.status >= 200 && xhr.status < 300) {
                    if (typeof callback === 'function') {
                        callback();
                    }
                } else if (window.console && window.console.error) {
                    window.console.error('FP Multilanguage: impossibile salvare la traduzione manuale', xhr.responseText);
                }
            }
        };
        xhr.send(params.toString());
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyManualStrings(document);
    });

    window.fpMultilanguageApplyManualStrings = applyManualStrings;
})(window, document);
