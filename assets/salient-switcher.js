(function () {
	'use strict';

	var resizeTimer = null;

	/**
	 * Execute callback when DOM is ready.
	 *
	 * @param {Function} callback Function to run when ready.
	 * @returns {void}
	 */
	function onReady(callback) {
		if (document.readyState !== 'loading') {
			callback();
			document.body.classList.add('fpml-salient-enhanced');
		} else {
			document.addEventListener('DOMContentLoaded', function () {
				callback();
				document.body.classList.add('fpml-salient-enhanced');
			});
		}
	}

	/**
	 * Inject the language switcher next to the Salient hamburger toggles.
	 *
	 * @returns {void}
	 */
	function mountSwitcher() {
		var header = document.getElementById('header-outer');

		if (!header) {
			return;
		}

		// Avoid duplicating wrappers - but only if we already have a mounted switcher with content
		var existingSwitcher = header.querySelector('.fpml-salient-switcher');
		if (existingSwitcher) {
			// Verifica se lo switcher esistente ha ancora contenuto valido
			var existingContent = existingSwitcher.querySelector('.fpml-switcher');
			if (existingContent && existingContent.querySelectorAll('a').length > 0) {
				return;
			}
			// Se lo switcher esiste ma è vuoto, rimuovilo e ricrea
			existingSwitcher.remove();
		}

	var sourceElement = header.querySelector('nav .menu-item-language-switcher.fpml-auto-integrated');
	var sourceType = 'menu';

		if (!sourceElement) {
		sourceElement = header.querySelector('[data-fpml-switcher-placeholder=\"true\"]');
			sourceType = 'placeholder';
		}

		// Se non c'è un placeholder, cerca uno switcher esistente nel DOM che possiamo usare
		if (!sourceElement) {
			var existingInlineSwitcher = header.querySelector('.fpml-switcher--inline');
			if (existingInlineSwitcher && existingInlineSwitcher.querySelectorAll('a').length > 0) {
				// Usa lo switcher esistente come sorgente
				sourceElement = existingInlineSwitcher.parentElement || existingInlineSwitcher;
				sourceType = 'existing';
			}
		}

		if (!sourceElement) {
			return;
		}

		var switcherContent = sourceElement.querySelector('.fpml-switcher');

		// Se non c'è contenuto nello switcher, prova a cercare direttamente i link
		if (!switcherContent) {
			var flagLinks = sourceElement.querySelectorAll('a');
			if (flagLinks.length === 0) {
				return;
			}
			// Crea un contenitore temporaneo
			switcherContent = document.createElement('div');
			switcherContent.className = 'fpml-switcher fpml-switcher--inline';
			flagLinks.forEach(function(link) {
				switcherContent.appendChild(link.cloneNode(true));
			});
		}

		var toggles = header.querySelectorAll('.slide-out-widget-area-toggle.mobile-icon');

		if (!toggles.length) {
			return;
		}

		// When the header is collapsed (mobile/compact) we skip duplication.
		var toggleWidth = toggles[0].getBoundingClientRect().width;
		if (toggles.length === 1 && toggleWidth > 0 && toggleWidth < 40) {
			return;
		}

		toggles.forEach(function (toggle, index) {
			if (!toggle || !toggle.parentNode) {
				return;
			}

			var wrapper = document.createElement('div');
			wrapper.className = 'fpml-salient-switcher';
			wrapper.setAttribute('data-fpml-switcher', 'salient');

			if (0 === index) {
				wrapper.appendChild(switcherContent);
			} else {
				wrapper.appendChild(switcherContent.cloneNode(true));
			}

			toggle.parentNode.insertBefore(wrapper, toggle);
		});

		// Rimuovi solo il placeholder, non lo switcher esistente che potrebbe essere ancora utile
		if (sourceType === 'menu') {
			sourceElement.remove();
		} else if (sourceType === 'placeholder') {
			sourceElement.remove();
		}
		// Non rimuovere se sourceType === 'existing' perché potrebbe essere ancora visibile
	}

	onReady(mountSwitcher);

	window.addEventListener('resize', function () {
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(mountSwitcher, 200);
	});

	document.addEventListener('fpml:refresh-switcher', mountSwitcher);
})();

