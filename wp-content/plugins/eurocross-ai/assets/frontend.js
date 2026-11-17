/**
 * EuroCross AI frontend behaviour.
 *
 * All requests go to WordPress REST routes under eurocross/v1. The SaaS API
 * key is never available to this script.
 */
(function () {
	'use strict';

	var config = window.EuroCrossData || {};
	var strings = config.strings || {};
	var restUrl = String(config.restUrl || '').replace(/\/+$/, '') + '/';
	var localeStorageKey = 'eurocross_locale';
	var cookieName = 'eurocross_locale';

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

	function apiRequest(route, payload) {
		var options = {
			method: payload === undefined ? 'GET' : 'POST',
			headers: {
				'Accept': 'application/json',
				'X-WP-Nonce': String(config.nonce || '')
			}
		};

		if (payload !== undefined) {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(payload);
		}

		return fetch(restUrl + route.replace(/^\/+/, ''), options).then(function (response) {
			return response.text().then(function (rawBody) {
				var parsed = {};
				try {
					parsed = rawBody ? JSON.parse(rawBody) : {};
				} catch (error) {
					throw new Error(text('error', 'Something went wrong. Please try again.'));
				}

				if (!response.ok) {
					var message = parsed.message || text('error', 'Something went wrong. Please try again.');
					throw new Error(String(message));
				}

				return parsed;
			});
		});
	}

	function readCookie() {
		var parts = document.cookie.split(';');
		for (var index = 0; index < parts.length; index += 1) {
			var part = parts[index].trim();
			if (part.indexOf(cookieName + '=') === 0) {
				return decodeURIComponent(part.slice(cookieName.length + 1));
			}
		}
		return '';
	}

	function isValidLocale(code) {
		if (!/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/.test(String(code || ''))) {
			return false;
		}
		if (!Array.isArray(config.languages) || config.languages.length === 0) {
			return true;
		}
		return config.languages.some(function (language) {
			return language && language.code === code;
		});
	}

	function getLocale() {
		var stored = '';
		try {
			stored = window.localStorage.getItem(localeStorageKey) || '';
		} catch (error) {
			stored = readCookie();
		}
		if (isValidLocale(stored)) {
			return stored;
		}
		if (isValidLocale(readCookie())) {
			return readCookie();
		}
		return String(config.locale || 'en');
	}

	function persistLocale(code) {
		try {
			window.localStorage.setItem(localeStorageKey, code);
		} catch (error) {
			// Storage can be disabled; the cookie below is the fallback.
		}
		document.cookie = cookieName + '=' + encodeURIComponent(code) + ';path=/;max-age=31536000;SameSite=Lax';
	}

	function switchLocale(code) {
		if (!isValidLocale(code) || code === getLocale()) {
			return Promise.resolve(false);
		}

		persistLocale(code);

		if (!config.canEdit) {
			window.location.reload();
			return Promise.resolve(true);
		}

		return apiRequest('locale', { locale: code }).then(function () {
			window.location.reload();
			return true;
		}).catch(function () {
			window.location.reload();
			return true;
		});
	}

	function setupLanguageSwitchers() {
		var selectors = document.querySelectorAll('[data-eurocross-language-switcher]');
		var current = getLocale();

		Array.prototype.forEach.call(selectors, function (select) {
			if (select.tagName !== 'SELECT') {
				return;
			}

			var hasCurrent = false;
			Array.prototype.forEach.call(select.options, function (option) {
				if (option.value === current) {
					hasCurrent = true;
				}
			});

			if (hasCurrent) {
				select.value = current;
			}

			select.setAttribute('aria-label', text('language', 'Language'));
			select.addEventListener('change', function () {
				switchLocale(select.value);
			});
		});
	}

	function setStatus(node, message, isError) {
		if (!node) {
			return;
		}
		node.textContent = message || '';
		node.setAttribute('role', isError ? 'alert' : 'status');
		node.classList.toggle('is-error', Boolean(isError));
	}

	function translate(textToTranslate, sourceLang, targetLang) {
		var payload = {
			text: String(textToTranslate || ''),
			source_lang: String(sourceLang || config.locale || 'en'),
			target_lang: String(targetLang || getLocale())
		};

		return apiRequest('translate', payload).then(function (response) {
			var data = response && response.data ? response.data : {};
			return {
				original: String(data.original || payload.text),
				translated: String(data.translated || ''),
				targetLanguage: String(data.target_language || payload.target_lang),
				provider: String(data.provider || ''),
				tokens: Number(data.tokens || 0),
				cost: String(data.cost || '')
			};
		});
	}

	function setupTranslationTriggers() {
		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('[data-eurocross-translate-trigger]');
			if (!trigger) {
				return;
			}

			event.preventDefault();
			var scope = trigger.closest('[data-eurocross-translate]') || document;
			var sourceNode = scope.querySelector('[data-eurocross-translate-source]') || trigger;
			var targetNode = scope.querySelector('[data-eurocross-translate-target]');
			var statusNode = scope.querySelector('[data-eurocross-translate-status]');
			var sourceText = sourceNode.getAttribute('data-original-text') || sourceNode.textContent.trim();

			if (!sourceNode.getAttribute('data-original-text')) {
				sourceNode.setAttribute('data-original-text', sourceText);
			}

			setStatus(statusNode, text('loading', 'Loading…'), false);
			trigger.disabled = true;

			translate(sourceText, sourceNode.getAttribute('data-source-lang'), getLocale()).then(function (result) {
				if (targetNode) {
					targetNode.textContent = result.translated || result.original;
				}
				setStatus(statusNode, text('translated', 'Translation complete.'), false);
			}).catch(function (error) {
				setStatus(statusNode, error.message, true);
			}).then(function () {
				trigger.disabled = false;
			});
		});
	}

	function appendChatMessage(container, author, message) {
		var item = document.createElement('p');
		var label = document.createElement('strong');
		label.textContent = author + ': ';
		item.appendChild(label);
		item.appendChild(document.createTextNode(message));
		container.appendChild(item);
		container.scrollTop = container.scrollHeight;
	}

	function setupChatbots() {
		if (!config.chatbotEnabled) {
			return;
		}

		var widgets = document.querySelectorAll('[data-eurocross-chatbot]');
		Array.prototype.forEach.call(widgets, function (widget) {
			var form = widget.querySelector('form');
			var messages = widget.querySelector('.eurocross-chatbot-messages');
			var status = widget.querySelector('.eurocross-chatbot-status');
			var button = widget.querySelector('button[type="submit"]');
			var input = widget.querySelector('textarea[name="message"]');

			if (!form || !messages || !input) {
				return;
			}

			// Keep a simple in-memory history so each request carries prior
			// turns as context, improving multi-turn conversations.
			var conversationHistory = [];

			form.addEventListener('submit', function (event) {
				event.preventDefault();
				var message = input.value.trim();
				if (!message) {
					return;
				}

				appendChatMessage(messages, text('you', 'You'), message);
				conversationHistory.push({ role: 'user', message: message });
				input.value = '';
				input.disabled = true;
				if (button) {
					button.disabled = true;
				}
				setStatus(status, text('thinking', 'Thinking…'), false);

				apiRequest('chat', {
					message: message,
					language: getLocale(),
					context: {
						url: String(window.location.href || '').slice(0, 500),
						history: conversationHistory.slice(-6)
					}
				}).then(function (response) {
					var data = response && response.data ? response.data : {};
					var reply = String(data.reply || '');
					appendChatMessage(messages, text('assistant', 'Assistant'), reply);
					conversationHistory.push({ role: 'assistant', message: reply });
					setStatus(status, '', false);
				}).catch(function (error) {
					setStatus(status, error.message, true);
				}).then(function () {
					input.disabled = false;
					if (button) {
						button.disabled = false;
					}
					input.focus();
				});
			});
		});
	}

	function setupVatFields() {
		var toggles = document.querySelectorAll('[data-eurocross-vat-toggle]');
		Array.prototype.forEach.call(toggles, function (toggle) {
			var form = toggle.closest('form') || document;
			var field = form.querySelector('[data-eurocross-vat-number]');
			if (!field) {
				return;
			}

			var applyToggle = function () {
				field.disabled = !toggle.checked;
				var row = field.closest('.eurocross-vat-row') || field.parentElement;
				if (row) {
					row.classList.toggle('is-disabled', !toggle.checked);
				}
				if (!toggle.checked) {
					field.value = '';
					var statusNode = form.querySelector('[data-eurocross-vat-status]');
					setStatus(statusNode, '', false);
				}
			};

			toggle.addEventListener('change', applyToggle);
			applyToggle();
		});

		document.addEventListener('change', function (event) {
			var field = event.target;
			if (!field || !field.matches || !field.matches('[data-eurocross-vat-number]')) {
				return;
			}

			var vatNumber = field.value.trim();
			var form = field.closest('form') || document;
			var statusNode = form.querySelector('[data-eurocross-vat-status]');
			if (!vatNumber) {
				setStatus(statusNode, '', false);
				return;
			}

			var countrySelect = form.querySelector('select[name="billing_country"], [data-eurocross-vat-country]');
			var country = countrySelect ? countrySelect.value : '';

			setStatus(statusNode, text('vatChecking', 'Checking VAT number…'), false);

			apiRequest('vat/check', {
				vat_number: vatNumber,
				customer_country: country
			}).then(function (response) {
				var data = response && response.data ? response.data : {};
				var parts = [];
				if (data.chargeVat === false) {
					parts.push(text('reverseCharge', 'Reverse charge applies.'));
				} else if (data.chargeVat === true) {
					parts.push(text('vatCharged', 'VAT is charged at checkout.'));
				}
				if (data.rate !== null && data.rate !== undefined) {
					parts.push(text('rate', 'Rate') + ': ' + data.rate + '%');
				}
				if (data.reason) {
					parts.push(String(data.reason));
				}
				setStatus(statusNode, parts.join(' ') || text('vatAvailable', 'VAT treatment updated.'), false);
			}).catch(function (error) {
				setStatus(statusNode, error.message, true);
			});
		});
	}

	ready(function () {
		setupLanguageSwitchers();
		setupTranslationTriggers();
		setupChatbots();
		setupVatFields();
	});

	window.EuroCrossAI = {
		request: apiRequest,
		translate: translate,
		getLocale: getLocale,
		switchLocale: switchLocale,
		validateVies: function (vatNumber) {
			return apiRequest('vat/validate-vies', { vat_number: String(vatNumber || '') });
		},
		checkVat: function (vatNumber, customerCountry, merchantCountry) {
			return apiRequest('vat/check', {
				vat_number: String(vatNumber || ''),
				customer_country: String(customerCountry || ''),
				merchant_country: String(merchantCountry || '')
			});
		}
	};
}());
