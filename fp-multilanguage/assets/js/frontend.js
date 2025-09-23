(function (window, document) {
    'use strict';

    function setLanguageCookie(language) {
        var date = new Date();
        date.setFullYear(date.getFullYear() + 1);
        document.cookie = 'fp_multilanguage_lang=' + language + ';expires=' + date.toUTCString() + ';path=/';
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target) {
            return;
        }

        if (target.matches('.fp-language-switcher a')) {
            var language = target.textContent.trim().toLowerCase();
            if (language) {
                setLanguageCookie(language);
                window.localStorage.setItem('fp_multilanguage_preference', language);
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        var stored = window.localStorage.getItem('fp_multilanguage_preference');
        if (stored) {
            setLanguageCookie(stored);
        }
    });
})(window, document);
