/**
 * JavaScript Delay Until Interaction
 *
 * This tiny script delays loading of non-critical JavaScript until user interaction.
 * Scripts are loaded when the user scrolls, clicks, touches, or presses a key.
 * A fallback timeout ensures scripts load even without interaction.
 *
 * @package WP_Performance_Optimizer
 */

(function() {
	'use strict';

	var loaded = false;
	var timeout = window.i365DelayTimeout || 5000;

	/**
	 * Load all delayed scripts.
	 */
	function loadScripts() {
		if (loaded) {
			return;
		}
		loaded = true;

		// Remove event listeners.
		var events = ['mousemove', 'scroll', 'touchstart', 'keydown', 'click'];
		events.forEach(function(event) {
			document.removeEventListener(event, loadScripts, { passive: true });
		});

		// Clear timeout if it exists.
		if (window.i365DelayTimer) {
			clearTimeout(window.i365DelayTimer);
		}

		// Find all delayed scripts.
		var scripts = document.querySelectorAll('script[data-i365-delay]');

		scripts.forEach(function(script) {
			var src = script.getAttribute('data-i365-src');
			if (src) {
				// Create a new script element to trigger download.
				var newScript = document.createElement('script');

				// Copy attributes.
				Array.from(script.attributes).forEach(function(attr) {
					if (attr.name !== 'data-i365-delay' && attr.name !== 'data-i365-src' && attr.name !== 'type') {
						newScript.setAttribute(attr.name, attr.value);
					}
				});

				// Set the actual src.
				newScript.src = src;

				// If original had defer, keep it.
				if (script.hasAttribute('defer')) {
					newScript.defer = true;
				}

				// Replace the placeholder.
				script.parentNode.replaceChild(newScript, script);
			}
		});
	}

	// Add event listeners for user interaction.
	var events = ['mousemove', 'scroll', 'touchstart', 'keydown', 'click'];
	events.forEach(function(event) {
		document.addEventListener(event, loadScripts, { once: true, passive: true });
	});

	// Fallback timeout to ensure scripts always load.
	window.i365DelayTimer = setTimeout(loadScripts, timeout);
})();
