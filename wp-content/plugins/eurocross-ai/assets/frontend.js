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
		if (!isValidLocale(code)) {
			return Promise.resolve(false);
		}

		var target = String(code || '').toLowerCase().trim();
		if (target === getLocale()) {
			return Promise.resolve(false);
		}

		persistLocale(target);

		var url = window.location.href;
		try {
			var parsed = new URL(url, window.location.origin);
			parsed.searchParams.set('eurocross_lang', target);
			parsed.searchParams.delete('lang');
			url = parsed.toString();
		} catch (e) {
			var separator = url.indexOf('?') === -1 ? '?' : '&';
			url = url.replace(/([?&])(?:eurocross_lang|lang)=[^&#]*/g, '').replace(/[?&]$/, '');
			separator = url.indexOf('?') === -1 ? '?' : '&';
			url += separator + 'eurocross_lang=' + encodeURIComponent(target);
		}

		if (!config.canEdit) {
			window.location.assign(url);
			return Promise.resolve(true);
		}

		return apiRequest('locale', { locale: target }).then(function () {
			window.location.assign(url);
			return true;
		}).catch(function () {
			window.location.assign(url);
			return true;
		});
	}

	function setupLanguageSwitchers() {
		var current = getLocale();

		var selectors = document.querySelectorAll('select[data-eurocross-language-switcher]');
		Array.prototype.forEach.call(selectors, function (select) {
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

		var dropdowns = document.querySelectorAll('[data-eurocross-language-dropdown]');
		Array.prototype.forEach.call(dropdowns, function (dropdown) {
			var toggle = dropdown.querySelector('[data-eurocross-language-toggle]');
			var menu = dropdown.querySelector('[data-eurocross-language-menu]');
			var options = dropdown.querySelectorAll('[data-eurocross-language-option]');
			var currentLabel = dropdown.querySelector('[data-eurocross-language-current]');
			if (!toggle || !menu) {
				return;
			}

			function closeMenu() {
				menu.hidden = true;
				toggle.setAttribute('aria-expanded', 'false');
				dropdown.classList.remove('is-open');
			}

			function openMenu() {
				menu.hidden = false;
				toggle.setAttribute('aria-expanded', 'true');
				dropdown.classList.add('is-open');
			}

			function syncSelection(code) {
				Array.prototype.forEach.call(options, function (option) {
					var active = option.getAttribute('data-locale') === code;
					option.classList.toggle('is-active', active);
					var item = option.closest('[role="option"]');
					if (item) {
						item.setAttribute('aria-selected', active ? 'true' : 'false');
					}
					if (active && currentLabel) {
						currentLabel.textContent = option.textContent;
					}
				});
			}

			syncSelection(current);

			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (dropdown.classList.contains('is-open')) {
					closeMenu();
				} else {
					openMenu();
				}
			});

			Array.prototype.forEach.call(options, function (option) {
				option.addEventListener('click', function (event) {
					event.preventDefault();
					var locale = option.getAttribute('data-locale') || '';
					syncSelection(locale);
					closeMenu();
					switchLocale(locale);
				});
			});

			document.addEventListener('click', function (event) {
				if (!dropdown.contains(event.target)) {
					closeMenu();
				}
			});

			dropdown.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					closeMenu();
					toggle.focus();
				}
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

	function appendChatMessage(container, author, message, role) {
		var kind = role || (author === text('you', 'You') ? 'user' : 'assistant');
		var item = document.createElement('div');
		item.className = 'eurocross-chatbot-message is-' + kind;
		var bubble = document.createElement('div');
		bubble.className = 'eurocross-chatbot-bubble';
		var label = document.createElement('span');
		label.className = 'eurocross-chatbot-author';
		label.textContent = author;
		bubble.appendChild(label);
		var body = document.createElement('span');
		body.className = 'eurocross-chatbot-body';
		body.textContent = String(message || '');
		bubble.appendChild(body);
		item.appendChild(bubble);
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
			var toggles = widget.querySelectorAll('[data-eurocross-chatbot-toggle]');

			Array.prototype.forEach.call(toggles, function (toggle) {
				toggle.addEventListener('click', function () {
					var isOpen = widget.classList.toggle('is-open');
					Array.prototype.forEach.call(toggles, function (btn) {
						btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
					});
					if (isOpen && input) {
						input.focus();
					}
				});
			});

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

				appendChatMessage(messages, text('you', 'You'), message, 'user');
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
					appendChatMessage(messages, text('assistant', 'Assistant'), reply, 'assistant');
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

	function shouldTranslateNode(node) {
		if (!node || node.nodeType !== Node.TEXT_NODE) return false;
		var parent = node.parentElement;
		if (!parent) return false;
		if (parent.closest('[data-eurocross-no-translate], script, style, noscript, code, pre')) return false;
		if (parent.closest('[data-eurocross-translated="1"]')) return false;
		var text = String(node.nodeValue || '').trim();
		if (text.length < 2 || text.length > 300) return false;
		if (!/[A-Za-z]/.test(text)) return false;
		if (/^[\d\s\.,:;/%$€£¥\-–—()#@!?'"`]+$/.test(text)) return false;
		return true;
	}

	function setupDynamicTranslation() {
		if (!config.locale || config.locale === config.sourceLocale) return;
		var queue = new Map();
		var timer = null;
		var inflight = false;
		var seen = new WeakSet();

		function flush() {
			timer = null;
			if (inflight || queue.size === 0) return;
			inflight = true;
			var entries = Array.prototype.slice.call(queue.entries()).slice(0, 12);
			entries.forEach(function (e) { queue.delete(e[0]); });
			var promises = entries.map(function (entry) {
				return apiRequest('translate', {
					text: entry[1].text,
					source_lang: config.sourceLocale || 'en',
					target_lang: config.locale
				}).then(function (response) {
					var data = response && response.data ? response.data : {};
					var node = entry[1].node;
					if (!node || !node.parentElement) return;
					var translated = String(data.translated || '');
					if (translated && translated !== entry[1].text) {
						node.nodeValue = node.nodeValue.replace(entry[1].text, translated);
						if (node.parentElement) node.parentElement.setAttribute('data-eurocross-translated', '1');
					}
				}).catch(function () {});
			});
			Promise.all(promises).then(function () {
				inflight = false;
				if (queue.size > 0) schedule();
			});
		}

		function schedule() {
			if (timer) return;
			timer = setTimeout(flush, 250);
		}

		function enqueue(node) {
			if (!shouldTranslateNode(node) || seen.has(node)) return;
			var text = String(node.nodeValue || '').trim();
			if (!text) return;
			seen.add(node);
			queue.set(node, { node: node, text: text });
			schedule();
		}

		function scan(root) {
			if (!root) return;
			var walkerRoot = root.nodeType === Node.TEXT_NODE ? root.parentElement : root;
			if (!walkerRoot || !walkerRoot.querySelectorAll) return;
			var selector = '.wc-block-components-product-name, .wc-block-cart-item__product, .wc-block-components-totals-item, .wc-block-formatted-money-amount, .wc-block-components-checkbox__label, .wc-block-components-radio-control__label, .wc-block-components-title, .wc-block-components-notice-banner, .wc-block-components-validation-error, .wc-block-cart__empty-cart__title, .wp-block-woocommerce-cart, .wp-block-woocommerce-checkout, .wp-block-woocommerce-mini-cart-contents';
			var containers = walkerRoot.matches && walkerRoot.matches(selector) ? [walkerRoot] : [];
			var found = walkerRoot.querySelectorAll(selector);
			Array.prototype.forEach.call(found, function (el) { containers.push(el); });
			containers.forEach(function (el) {
				var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
				var n;
				while ((n = walker.nextNode())) enqueue(n);
			});
		}

		function patchWcSettings() {
			var w = window.wcSettings;
			if (!w || typeof w !== 'object') return;
			var pairs = [];
			function collect(obj, path) {
				if (!obj || typeof obj !== 'object') return;
				Object.keys(obj).forEach(function (k) {
					var v = obj[k];
					var p = path ? path + '.' + k : k;
					if (typeof v === 'string') {
						var isLabelKey = /(label|optionalLabel|text|title|placeholder|message|description|name)/i.test(k);
						var isValue = v.length > 1 && v.length < 120 && /[A-Za-z]/.test(v) && !/^https?:\/\//.test(v) && !/^\d/.test(v);
						if (isLabelKey && isValue) pairs.push({ obj: obj, key: k, text: v });
					} else if (v && typeof v === 'object' && !Array.isArray(v) && path.split('.').length < 4) {
						collect(v, p);
					}
				});
			}
			collect(w, '');
			pairs.slice(0, 40).forEach(function (item) {
				translate(item.text, config.sourceLocale || 'en', config.locale).then(function (r) {
					if (r && r.translated && r.translated !== item.text) item.obj[item.key] = r.translated;
				}).catch(function () {});
			});
		}

		ready(function () { patchWcSettings(); scan(document.body); });
		var obs = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				Array.prototype.forEach.call(m.addedNodes || [], function (node) {
					if (node.nodeType === Node.TEXT_NODE) enqueue(node);
					else if (node.nodeType === Node.ELEMENT_NODE) scan(node);
				});
			});
		});
		if (document.body) obs.observe(document.body, { childList: true, subtree: true });
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

	function setupNavLanguageDropdown() {
		if (!Array.isArray(config.languages) || config.languages.length === 0) {
			return;
		}

		var navLinks = document.querySelectorAll('.wp-block-navigation a, nav a');
		var target = null;
		Array.prototype.forEach.call(navLinks, function (link) {
			if (target) {
				return;
			}
			var label = (link.textContent || '').toLowerCase().trim();
			var href = (link.getAttribute('href') || '').toLowerCase();
			if (label.indexOf('language') !== -1 || href.indexOf('language-chat') !== -1) {
				target = link;
			}
		});

		if (!target || target.closest('[data-eurocross-switcher-root]')) {
			return;
		}

		var parent = target.closest('li') || target.parentElement;
		if (!parent) {
			return;
		}

		var current = getLocale();
		var currentName = text('language', 'Language');
		config.languages.forEach(function (language) {
			if (language && language.code === current) {
				currentName = language.native_name || language.name || currentName;
			}
		});

		var root = document.createElement('div');
		root.className = 'eurocross-language-switcher eurocross-nav-language-switcher';
		root.setAttribute('data-eurocross-switcher-root', '');
		root.innerHTML = '' +
			'<div class="eurocross-language-dropdown" data-eurocross-language-dropdown>' +
				'<button type="button" class="eurocross-language-toggle eurocross-nav-language-toggle" data-eurocross-language-toggle aria-haspopup="listbox" aria-expanded="false">' +
					'<span class="eurocross-language-toggle-label" data-eurocross-language-current></span>' +
					'<span class="eurocross-language-caret" aria-hidden="true"></span>' +
				'</button>' +
				'<ul class="eurocross-language-menu" data-eurocross-language-menu role="listbox" aria-label="' + text('language', 'Language') + '" hidden></ul>' +
			'</div>';

		var menu = root.querySelector('[data-eurocross-language-menu]');
		var labelNode = root.querySelector('[data-eurocross-language-current]');
		if (labelNode) {
			labelNode.textContent = currentName;
		}

		config.languages.forEach(function (language) {
			if (!language || !language.code) {
				return;
			}
			var name = language.native_name || language.name || language.code;
			var li = document.createElement('li');
			li.setAttribute('role', 'option');
			li.setAttribute('aria-selected', language.code === current ? 'true' : 'false');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'eurocross-language-option' + (language.code === current ? ' is-active' : '');
			btn.setAttribute('data-eurocross-language-option', '');
			btn.setAttribute('data-locale', language.code);
			btn.textContent = name;
			li.appendChild(btn);
			menu.appendChild(li);
		});

		target.replaceWith(root);
	}

	ready(function () {
		setupNavLanguageDropdown();
		setupLanguageSwitchers();
		setupTranslationTriggers();
		setupChatbots();
		setupDynamicTranslation();
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
