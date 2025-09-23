(function (window, document, wp) {
    'use strict';

    var config = window.fpMultilanguageSettings || {};
    var container = document.getElementById('fp-multilanguage-settings-app');

    if (!container || !config.restUrl) {
        return;
    }

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
})(window, document, window.wp || {});
