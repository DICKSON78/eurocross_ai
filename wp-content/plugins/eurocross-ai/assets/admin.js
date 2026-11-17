/**
 * EuroCross AI admin behaviour.
 *
 * All actions use admin-ajax with nonces supplied by WordPress. The SaaS API
 * key is never exposed to JavaScript.
 */
(function () {
	'use strict';

	var config = window.EuroCrossAdminData || {};
	var strings = config.strings || {};
	var ajaxUrl = String(config.ajaxUrl || '');

	function text(key, fallback) {
		return Object.prototype.hasOwnProperty.call(strings, key) ? String(strings[key]) : fallback;
	}

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
		} else {
			callback();
		}
	}

	function setStatus(node, message, isError) {
		if (!node) {
			return;
		}
		node.textContent = message || '';
		node.classList.toggle('is-error', Boolean(isError));
	}

	function postToAdmin(formData) {
		if (!ajaxUrl) {
			return Promise.reject(new Error(text('error', 'The request failed. Please try again.')));
		}

		return fetch(ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.text().then(function (rawBody) {
				var parsed = {};
				try {
					parsed = rawBody ? JSON.parse(rawBody) : {};
				} catch (error) {
					throw new Error(text('error', 'The request failed. Please try again.'));
				}

				if (!response.ok || !parsed.success) {
					var message = parsed.data && parsed.data.message ? parsed.data.message : text('error', 'The request failed. Please try again.');
					throw new Error(String(message));
				}

				return parsed.data || {};
			});
		});
	}

	function setupTabs() {
		var tabs = document.querySelectorAll('[data-eurocross-tab]');
		if (!tabs.length) {
			return;
		}

		Array.prototype.forEach.call(tabs, function (tab) {
			tab.addEventListener('click', function (event) {
				event.preventDefault();
				Array.prototype.forEach.call(tabs, function (item) {
					item.classList.remove('is-active');
				});
				tab.classList.add('is-active');

				var target = document.getElementById(tab.getAttribute('data-eurocross-tab'));
				if (target && typeof target.scrollIntoView === 'function') {
					target.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
				if (window.history && typeof window.history.replaceState === 'function') {
					window.history.replaceState(null, '', '#' + tab.getAttribute('data-eurocross-tab'));
				}
			});
		});
	}

	function setupConnectionTest() {
		var button = document.querySelector('[data-eurocross-test-connection]');
		var status = document.querySelector('[data-eurocross-connection-status]');
		if (!button) {
			return;
		}

		button.addEventListener('click', function () {
			var formData = new FormData();
			formData.append('action', 'eurocross_test_connection');
			formData.append('nonce', String(config.nonce || ''));

			button.disabled = true;
			setStatus(status, text('testing', 'Testing connection…'), false);

			postToAdmin(formData).then(function (data) {
				setStatus(status, data.message || text('connected', 'Connection successful.'), false);
			}).catch(function (error) {
				setStatus(status, text('failed', 'Connection failed.') + ' ' + error.message, true);
			}).then(function () {
				button.disabled = false;
			});
		});
	}

	function setupActionForms() {
		var forms = document.querySelectorAll('[data-eurocross-ajax-form]');
		Array.prototype.forEach.call(forms, function (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();

				var action = form.getAttribute('data-eurocross-ajax-form');
				var status = form.querySelector('.eurocross-ajax-status');
				var button = form.querySelector('button[type="submit"]');
				var formData = new FormData(form);

				formData.set('action', action);

				// Use action-specific nonces when available, fall back to the
				// shared admin nonce so each AJAX handler's check_ajax_referer
				// receives the correct token.
				if (action === 'eurocross_start_trial' && config.nonceTrial) {
					formData.set('_wpnonce', String(config.nonceTrial));
				} else if (action === 'eurocross_checkout' && config.nonceCheckout) {
					formData.set('_wpnonce', String(config.nonceCheckout));
				} else {
					formData.set('nonce', String(config.nonce || ''));
				}

				if (button) {
					button.disabled = true;
				}
				setStatus(status, text('processing', 'Processing…'), false);

				postToAdmin(formData).then(function (data) {
					if (action === 'eurocross_checkout' && data.checkout_url) {
						setStatus(status, data.message || text('redirect', 'Opening checkout…'), false);
						var url = new URL(String(data.checkout_url), window.location.href);
						if (url.protocol === 'https:') {
							window.location.assign(url.href);
						} else {
							throw new Error(text('error', 'The request failed. Please try again.'));
						}
						return;
					}
					setStatus(status, data.message || text('trialDone', 'Trial started.'), false);
				}).catch(function (error) {
					setStatus(status, error.message, true);
				}).then(function () {
					if (button) {
						button.disabled = false;
					}
				});
			});
		});
	}

	function setupDashboardRefresh() {
		var link = document.querySelector('[data-eurocross-refresh]');
		var status = document.querySelector('[data-eurocross-refresh-status]');
		var loading = document.querySelector('[data-eurocross-loading]');
		if (!link) {
			return;
		}

		link.addEventListener('click', function () {
			link.classList.add('is-loading');
			setStatus(status, text('refreshing', 'Loading…'), false);
			if (loading) {
				loading.hidden = false;
			}
		});
	}

	ready(function () {
		setupTabs();
		setupConnectionTest();
		setupActionForms();
		setupDashboardRefresh();
	});
}());
