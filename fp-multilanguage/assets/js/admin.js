(function (window, document, wp) {
    'use strict';

    var config = window.fpMultilanguageSettings || {};
    var container = document.getElementById('fp-multilanguage-settings-app');

    if (container && config.restUrl) {
        function renderStatus(message, type) {
            var notice = document.createElement('div');
            notice.className = 'notice notice-' + (type || 'info') + ' is-dismissible';
            notice.innerHTML = '<p>' + message + '</p>';
            container.innerHTML = '';
            container.appendChild(notice);
        }

        function syncSettings() {
            fetch(config.restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce
                },
                body: JSON.stringify(config.options)
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    return response.json();
                })
                .then(function () {
                    renderStatus('Impostazioni sincronizzate con successo.', 'success');
                })
                .catch(function () {
                    renderStatus('Impossibile sincronizzare le impostazioni via REST.', 'error');
                });
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-secondary';
        button.textContent = 'Sincronizza via REST';
        button.addEventListener('click', syncSettings);

        container.appendChild(button);
    }

    function getMessage(key, fallback) {
        var i18n = config.i18n || {};

        return i18n[key] || fallback;
    }

    function extractProviderKey(name, provider) {
        var pattern = new RegExp('\\[providers\\]\\[' + provider + '\\]\\[([^\\]]+)\\]');
        var match = name.match(pattern);

        return match && match[1] ? match[1] : null;
    }

    var optionName = config.optionName || 'fp_multilanguage_options';

    function collectProviderOptions(form, provider) {
        var selector = '[name^="' + optionName + '[providers][' + provider + ']"]';
        var fields = form.querySelectorAll(selector);
        var options = {};

        fields.forEach(function (field) {
            var key = extractProviderKey(field.name, provider);

            if (!key) {
                return;
            }

            if (field.type === 'checkbox') {
                options[key] = field.checked;
            } else if (field.type === 'number') {
                options[key] = field.value === '' ? '' : parseInt(field.value, 10);
            } else {
                options[key] = field.value;
            }
        });

        return options;
    }

    function updateProviderStatus(provider, status, message) {
        var statusEl = document.querySelector('.fp-multilanguage-provider-status[data-provider="' + provider + '"]');

        if (!statusEl) {
            return;
        }

        statusEl.classList.remove('is-success', 'is-error', 'is-loading');

        if (status) {
            statusEl.classList.add('is-' + status);
        }

        statusEl.textContent = message;

        if (statusEl.dataset) {
            statusEl.dataset.state = status || '';
        }
    }

    function resetProviderStatus(provider) {
        var statusEl = document.querySelector('.fp-multilanguage-provider-status[data-provider="' + provider + '"]');

        if (!statusEl) {
            return;
        }

        statusEl.classList.remove('is-success', 'is-error', 'is-loading');
        statusEl.textContent = '';

        if (statusEl.dataset) {
            statusEl.dataset.state = '';
        }
    }

    function handleProviderTest(event) {
        event.preventDefault();

        var button = event.currentTarget;
        var provider = button.getAttribute('data-provider');

        if (!provider || !config.testUrl || !config.nonce) {
            return;
        }

        var form = button.closest('form');

        if (!form) {
            return;
        }

        button.setAttribute('disabled', 'disabled');
        updateProviderStatus(provider, 'loading', getMessage('testing', 'Verifica in corso…'));

        var payload = {
            provider: provider,
            options: collectProviderOptions(form, provider)
        };

        fetch(config.testUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce
            },
            body: JSON.stringify(payload)
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }

                return response.json();
            })
            .then(function (data) {
                var success = data && typeof data.success === 'boolean' ? data.success : false;
                var message = data && data.message ? data.message : getMessage('unknownError', 'La verifica non è riuscita.');

                updateProviderStatus(provider, success ? 'success' : 'error', message);
                button.removeAttribute('disabled');
            })
            .catch(function () {
                updateProviderStatus(provider, 'error', getMessage('networkError', 'Errore di rete durante la verifica.'));
                button.removeAttribute('disabled');
            });
    }

    var testButtons = document.querySelectorAll('.fp-multilanguage-provider-test');

    testButtons.forEach(function (button) {
        button.addEventListener('click', handleProviderTest);
    });

    if (config.testUrl) {
        var providerInputs = document.querySelectorAll('.fp-multilanguage-provider input, .fp-multilanguage-provider select');

        providerInputs.forEach(function (input) {
            input.addEventListener('input', function (event) {
                var providerWrapper = event.currentTarget.closest('.fp-multilanguage-provider');

                if (!providerWrapper) {
                    return;
                }

                var providerName = providerWrapper.getAttribute('data-provider');

                if (providerName) {
                    resetProviderStatus(providerName);
                }
            });
        });
    }

    var stringsConfig = window.fpMultilanguageStrings || {};
    var stringsSearch = document.getElementById('fp-multilanguage-strings-search');
    var stringsTable = document.getElementById('fp-multilanguage-strings-table');
    var stringsEmpty = document.getElementById('fp-multilanguage-strings-empty');

    if (stringsSearch && stringsTable) {
        var rows = stringsTable.querySelectorAll('tbody tr');

        function filterStrings() {
            var query = stringsSearch.value ? stringsSearch.value.toLowerCase() : '';
            var visible = 0;

            Array.prototype.forEach.call(rows, function (row) {
                var text = row.textContent ? row.textContent.toLowerCase() : '';
                var match = !query || text.indexOf(query) !== -1;

                row.style.display = match ? '' : 'none';

                if (match) {
                    visible += 1;
                }
            });

            if (stringsEmpty) {
                stringsEmpty.style.display = visible === 0 ? '' : 'none';

                if (visible === 0 && stringsConfig.noResults) {
                    stringsEmpty.textContent = stringsConfig.noResults;
                }
            }
        }

        stringsSearch.addEventListener('input', filterStrings);
        filterStrings();
    }

    var onboarding = window.fpMultilanguageOnboarding || {};
    var wizard = document.getElementById('fp-multilanguage-onboarding');

    function getOnboardingMessage(key, fallback) {
        if (!onboarding || !onboarding.i18n) {
            return fallback;
        }

        return onboarding.i18n[key] || fallback;
    }

    if (wizard) {
        var form = wizard.querySelector('form');
        var steps = wizard.querySelectorAll('.fp-multilanguage-step');
        var progressItems = wizard.querySelectorAll('.fp-multilanguage-steps [data-step]');
        var notice = wizard.querySelector('.fp-multilanguage-onboarding-notice');
        var nextButton = wizard.querySelector('[data-action="next"]');
        var prevButton = wizard.querySelector('[data-action="prev"]');
        var submitButton = wizard.querySelector('[data-action="submit"]');
        var totalSteps = steps.length;
        var currentStep = 1;

        function showNotice(type, message) {
            if (!notice) {
                return;
            }

            notice.textContent = message || '';
            notice.classList.remove('notice-error', 'notice-success', 'notice-info');

            if (type && message) {
                notice.classList.add('notice', 'notice-' + type);
                notice.style.display = '';
            } else {
                notice.classList.remove('notice');
                notice.style.display = 'none';
            }
        }

        function updateSummary() {
            if (!form) {
                return;
            }

            var summaryTargets = wizard.querySelectorAll('[data-summary]');

            if (!summaryTargets.length) {
                return;
            }

            var sourceInput = form.querySelector('[name="' + optionName + '[source_language]"]');
            var fallbackInput = form.querySelector('[name="' + optionName + '[fallback_language]"]');
            var targetsInput = form.querySelector('[name="' + optionName + '[target_languages]"]');
            var autoInput = form.querySelector('[name="' + optionName + '[auto_translate]"]');
            var taxonomySelect = form.querySelector('[name="' + optionName + '[taxonomies][]"]');
            var providerCards = form.querySelectorAll('.fp-multilanguage-provider');

            summaryTargets.forEach(function (element) {
                var key = element.getAttribute('data-summary');

                if (!key) {
                    return;
                }

                if (key === 'source' && sourceInput) {
                    element.textContent = sourceInput.value || getOnboardingMessage('emptyValue', '—');
                } else if (key === 'fallback' && fallbackInput) {
                    element.textContent = fallbackInput.value || getOnboardingMessage('emptyValue', '—');
                } else if (key === 'targets' && targetsInput) {
                    var raw = targetsInput.value || '';
                    var list = raw
                        .split(',')
                        .map(function (value) {
                            return value.trim();
                        })
                        .filter(function (value) {
                            return value !== '';
                        });

                    element.textContent = list.length ? list.join(', ') : getOnboardingMessage('emptyValue', '—');
                } else if (key === 'auto' && autoInput) {
                    var yes = getOnboardingMessage('autoEnabled', 'Attivo');
                    var no = getOnboardingMessage('autoDisabled', 'Disattivo');
                    element.textContent = autoInput.checked ? yes : no;
                } else if (key === 'providers') {
                    var enabled = [];

                    providerCards.forEach(function (card) {
                        var checkbox = card.querySelector('input[type="checkbox"][name$="[enabled]"]');

                        if (checkbox && checkbox.checked) {
                            var label = card.getAttribute('data-provider-label') || card.getAttribute('data-provider');
                            if (label) {
                                enabled.push(label);
                            }
                        }
                    });

                    if (!enabled.length) {
                        element.textContent = getOnboardingMessage('providersDisabled', 'Nessun provider attivo');
                    } else {
                        element.textContent = enabled.join(', ');
                    }
                } else if (key === 'taxonomies') {
                    var selectedTaxonomies = [];

                    if (taxonomySelect && taxonomySelect.options) {
                        Array.prototype.forEach.call(taxonomySelect.options, function (option) {
                            if (option.selected && option.value) {
                                var label = option.textContent || option.value;
                                label = label.trim();

                                if (label) {
                                    selectedTaxonomies.push(label);
                                }
                            }
                        });
                    }

                    element.textContent = selectedTaxonomies.length
                        ? selectedTaxonomies.join(', ')
                        : getOnboardingMessage('emptyValue', '—');
                }
            });
        }

        function updateProgress() {
            if (!progressItems.length) {
                return;
            }

            progressItems.forEach(function (item) {
                var stepValue = parseInt(item.getAttribute('data-step'), 10);

                item.classList.remove('is-active', 'is-complete');

                if (stepValue < currentStep) {
                    item.classList.add('is-complete');
                } else if (stepValue === currentStep) {
                    item.classList.add('is-active');
                }
            });
        }

        function showStep(step) {
            steps.forEach(function (section) {
                var stepValue = parseInt(section.getAttribute('data-step'), 10);

                if (stepValue === step) {
                    section.removeAttribute('hidden');
                } else {
                    section.setAttribute('hidden', 'hidden');
                }
            });

            if (prevButton) {
                if (step <= 1) {
                    prevButton.setAttribute('disabled', 'disabled');
                    prevButton.style.display = 'none';
                } else {
                    prevButton.removeAttribute('disabled');
                    prevButton.style.display = '';
                }
            }

            if (nextButton) {
                nextButton.style.display = step >= totalSteps ? 'none' : '';
            }

            if (submitButton) {
                submitButton.style.display = step === totalSteps ? '' : 'none';
            }

            updateProgress();
            updateSummary();
            showNotice('', '');
        }

        function validateStep(step) {
            if (step !== 2) {
                return true;
            }

            var cards = wizard.querySelectorAll('.fp-multilanguage-provider');
            var missing = [];

            cards.forEach(function (card) {
                var checkbox = card.querySelector('input[type="checkbox"][name$="[enabled]"]');
                var status = card.querySelector('.fp-multilanguage-provider-status');

                if (!checkbox || !checkbox.checked) {
                    return;
                }

                var isValid = status && status.dataset && status.dataset.state === 'success';

                if (!isValid) {
                    missing.push(card.getAttribute('data-provider-label') || card.getAttribute('data-provider') || '');
                }
            });

            if (missing.length) {
                var template = getOnboardingMessage('validationProviders', 'Verifica le credenziali prima di continuare: %s');
                var message = template.replace('%s', missing.join(', '));

                showNotice('error', message);

                return false;
            }

            return true;
        }

        function goTo(step) {
            if (step > currentStep && !validateStep(currentStep)) {
                return;
            }

            if (step < 1) {
                step = 1;
            }

            if (step > totalSteps) {
                step = totalSteps;
            }

            currentStep = step;
            showStep(currentStep);
        }

        if (nextButton) {
            nextButton.addEventListener('click', function (event) {
                event.preventDefault();
                goTo(currentStep + 1);
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', function (event) {
                event.preventDefault();
                goTo(currentStep - 1);
            });
        }

        if (form) {
            form.addEventListener('input', function (event) {
                var target = event.target;

                if (target && target.closest('.fp-multilanguage-provider')) {
                    var providerWrapper = target.closest('.fp-multilanguage-provider');

                    if (providerWrapper) {
                        var providerName = providerWrapper.getAttribute('data-provider');

                        if (providerName) {
                            resetProviderStatus(providerName);
                        }
                    }
                }

                updateSummary();
            });

            form.addEventListener('change', function (event) {
                var target = event.target;

                if (target && target.type === 'checkbox' && target.closest('.fp-multilanguage-provider')) {
                    var providerWrapper = target.closest('.fp-multilanguage-provider');

                    if (providerWrapper) {
                        var providerName = providerWrapper.getAttribute('data-provider');

                        if (providerName) {
                            resetProviderStatus(providerName);
                        }
                    }
                }

                updateSummary();
            });
        }

        showStep(currentStep);
    }
})(window, document, window.wp || {});
