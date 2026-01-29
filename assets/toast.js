/**
 * Toast Notifications (Vanilla JS - no React).
 *
 * @package FP_Multilanguage
 * @since 0.5.0
 */

const FPMLToast = {
	show: function(message, type = 'success', duration = 3000) {
		const toast = document.createElement('div');
		toast.className = `fpml-toast fpml-toast-${type}`;
		toast.innerHTML = `
			<span class="fpml-toast-icon">${this.getIcon(type)}</span>
			<span class="fpml-toast-message">${message}</span>
			<button class="fpml-toast-close" onclick="this.parentElement.remove()">&times;</button>
		`;

		document.body.appendChild(toast);

		// Animate in
		setTimeout(() => toast.classList.add('fpml-toast-show'), 10);

		// Auto remove
		setTimeout(() => {
			toast.classList.remove('fpml-toast-show');
			setTimeout(() => toast.remove(), 300);
		}, duration);
	},

	getIcon: function(type) {
		const icons = {
			success: '✓',
			error: '✗',
			warning: '⚠',
			info: 'ℹ'
		};
		return icons[type] || icons.info;
	},

	success: function(message) {
		this.show(message, 'success');
	},

	error: function(message) {
		this.show(message, 'error', 5000);
	},

	warning: function(message) {
		this.show(message, 'warning', 4000);
	},

	info: function(message) {
		this.show(message, 'info');
	}
};

// Global access
window.FPMLToast = FPMLToast;

