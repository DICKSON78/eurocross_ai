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
	var restUrl = String(config.restUrl || '').replace(/\/$/, '');
	var restNonce = String(config.wpRestNonce || '');

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

	function escapeHtml(value) {
		return String(value === null || typeof value === 'undefined' ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function apiErrorMessage(payload, fallback) {
		if (!payload || typeof payload !== 'object') {
			return fallback;
		}

		var message = fallback;
		if (typeof payload.message === 'string' && payload.message !== '') {
			message = payload.message;
		} else if (typeof payload.error === 'string' && payload.error !== '') {
			message = payload.error;
		} else if (payload.data && typeof payload.data === 'object') {
			return apiErrorMessage(payload.data, fallback);
		}

		var context = payload.context || (payload.data && payload.data.context);
		if (context && typeof context === 'object') {
			var details = [];
			if (typeof context.used === 'number') {
				details.push('used ' + context.used);
			}
			if (typeof context.reserved === 'number') {
				details.push('reserved ' + context.reserved);
			}
			if (typeof context.limit === 'number') {
				details.push('limit ' + context.limit);
			}
			if (typeof context.requested === 'number') {
				details.push('requested ' + context.requested);
			}
			if (details.length) {
				message += ' (' + details.join(', ') + ')';
			}
		}

		return message;
	}

	function restRequest(path, options) {
		if (!restUrl) {
			return Promise.reject(new Error(text('error', 'The request failed. Please try again.')));
		}

		options = options || {};
		var headers = { 'X-WP-Nonce': restNonce };
		if (options.body) {
			headers['Content-Type'] = 'application/json';
		}

		return fetch(restUrl + path, {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: headers,
			body: options.body ? JSON.stringify(options.body) : undefined
		}).then(function (response) {
			return response.text().then(function (rawBody) {
				var payload = {};
				try {
					payload = rawBody ? JSON.parse(rawBody) : {};
				} catch (error) {
					payload = {};
				}

				if (!response.ok) {
					var failure = new Error(apiErrorMessage(payload, text('error', 'The request failed. Please try again.')));
					failure.payload = payload;
					failure.status = response.status;
					throw failure;
				}

				return payload;
			});
		});
	}

	function setupTranslations() {
		var root = document.querySelector('[data-eurocross-translations]');
		if (!root) {
			return;
		}

		var statusNode = root.querySelector('[data-eurocross-translation-status]');
		var summaryNode = root.querySelector('[data-eurocross-translation-summary]');
		var filterForm = root.querySelector('[data-eurocross-translation-filter-form]');
		var rowsNode = root.querySelector('[data-eurocross-translation-rows]');
		var pageNode = root.querySelector('[data-eurocross-translation-page]');
		var prevButton = root.querySelector('[data-eurocross-page-prev]');
		var nextButton = root.querySelector('[data-eurocross-page-next]');
		var selectAll = root.querySelector('[data-eurocross-select-all]');
		var selectedCount = root.querySelector('[data-eurocross-selected-count]');
		var bulkAction = root.querySelector('[data-eurocross-bulk-action]');
		var bulkApply = root.querySelector('[data-eurocross-bulk-apply]');
		var modal = root.querySelector('[data-eurocross-edit-modal]');
		var editSource = root.querySelector('[data-eurocross-edit-source]');
		var editText = root.querySelector('[data-eurocross-edit-text]');
		var editStatus = root.querySelector('[data-eurocross-edit-status]');
		var editSave = root.querySelector('[data-eurocross-edit-save]');

		var state = {
			rows: [],
			page: 1,
			lastPage: 1,
			perPage: 20,
			editingId: 0
		};

		function filters() {
			var form = new FormData(filterForm);
			return {
				status: String(form.get('status') || ''),
				target_lang: String(form.get('target_lang') || ''),
				source_type: String(form.get('source_type') || ''),
				per_page: parseInt(form.get('per_page'), 10) || 20
			};
		}

		function renderSummary(data) {
			if (!summaryNode || !data) {
				return;
			}

			var totals = data.totals || {};
			var quota = data.quota || {};
			var cards = [
				[totals.translations || 0, text('totalTranslations', 'Total translations')],
				[totals.words || 0, text('totalWords', 'Words translated')],
				[(data.statuses && data.statuses.failed && data.statuses.failed.total) || 0, text('failedTranslations', 'Failed translations')],
				[totals.manual_edits || 0, text('manualEdits', 'Manual edits')],
				[typeof quota.remaining === 'number' ? quota.remaining : 0, text('quotaRemaining', 'Words remaining')],
				[totals.recent_translations || 0, text('recentTranslations', 'Recent translations') + ' (' + String(data.window_days || 30) + 'd)'],
				[totals.batches || 0, text('batches', 'Batches')]
			];

			summaryNode.innerHTML = cards.map(function (card) {
				return '<div class="eurocross-stat"><strong>' + escapeHtml(card[0]) + '</strong><span>' + escapeHtml(card[1]) + '</span></div>';
			}).join('');
		}

		function renderRows(data) {
			state.rows = Array.isArray(data.data) ? data.data : [];
			var meta = data.meta || {};
			state.page = parseInt(meta.current_page, 10) || 1;
			state.lastPage = parseInt(meta.last_page, 10) || 1;

			if (state.rows.length === 0) {
				rowsNode.innerHTML = '<tr><td colspan="9">' + escapeHtml(text('noTranslations', 'No translations match these filters.')) + '</td></tr>';
			} else {
				rowsNode.innerHTML = state.rows.map(function (row) {
					var preview = row.translated_text || row.source_text || '';
					preview = String(preview).replace(/\s+/g, ' ').trim();
					if (preview.length > 90) {
						preview = preview.substring(0, 90) + '…';
					}

					return '<tr>'
						+ '<td><input type="checkbox" data-eurocross-row-check value="' + escapeHtml(row.id) + '" aria-label="' + escapeHtml(row.id) + '" /></td>'
						+ '<td>' + escapeHtml(row.id) + '</td>'
						+ '<td>' + escapeHtml(row.source_type) + ' #' + escapeHtml(row.source_id) + '</td>'
						+ '<td>' + escapeHtml(row.field) + '</td>'
						+ '<td>' + escapeHtml(row.source_language) + ' → ' + escapeHtml(row.target_language) + '</td>'
						+ '<td><span class="eurocross-status eurocross-status--' + escapeHtml(row.status) + '">' + escapeHtml(row.status) + '</span></td>'
						+ '<td>' + escapeHtml(row.words) + '</td>'
						+ '<td class="eurocross-translation-preview">' + escapeHtml(preview) + '</td>'
						+ '<td class="eurocross-translation-actions">'
							+ '<button type="button" class="button button-small" data-eurocross-edit="' + escapeHtml(row.id) + '">' + escapeHtml(text('edit', 'Edit')) + '</button> '
							+ '<button type="button" class="button button-small" data-eurocross-retry="' + escapeHtml(row.id) + '">' + escapeHtml(text('retry', 'Retry')) + '</button>'
							+ (row.manually_edited ? ' <span class="eurocross-manual">' + escapeHtml(text('manual', 'Manual')) + '</span>' : '')
						+ '</td>'
						+ '</tr>';
				}).join('');
			}

			if (pageNode) {
				pageNode.textContent = text('page', 'Page') + ' ' + state.page + ' ' + text('of', 'of') + ' ' + state.lastPage;
			}
			if (prevButton) {
				prevButton.disabled = state.page <= 1;
			}
			if (nextButton) {
				nextButton.disabled = state.page >= state.lastPage;
			}
			if (selectAll) {
				selectAll.checked = false;
			}
			updateSelectedCount();
		}

		function loadSummary() {
			setStatus(statusNode, '', false);
			restRequest('/translations/summary?days=30').then(function (payload) {
				renderSummary(payload.data || {});
			}).catch(function (error) {
				setStatus(statusNode, text('summaryError', 'The translation summary could not be loaded.') + ' ' + error.message, true);
			});
		}

		function loadTranslations() {
			var current = filters();
			state.perPage = current.per_page;
			var query = [];
			Object.keys(current).forEach(function (key) {
				if (current[key] !== '') {
					query.push(encodeURIComponent(key) + '=' + encodeURIComponent(current[key]));
				}
			});
			query.push('page=' + encodeURIComponent(state.page));

			setStatus(statusNode, text('loadingTranslations', 'Loading translations…'), false);
			restRequest('/translations?' + query.join('&')).then(function (payload) {
				setStatus(statusNode, '', false);
				renderRows(payload);
			}).catch(function (error) {
				setStatus(statusNode, text('loadError', 'Translations could not be loaded.') + ' ' + error.message, true);
			});
		}

		function selectedRows() {
			var ids = [];
			Array.prototype.forEach.call(root.querySelectorAll('[data-eurocross-row-check]:checked'), function (checkbox) {
				ids.push(parseInt(checkbox.value, 10));
			});
			return ids;
		}

		function updateSelectedCount() {
			if (selectedCount) {
				selectedCount.textContent = selectedRows().length;
			}
		}

		function findRow(id) {
			for (var index = 0; index < state.rows.length; index++) {
				if (parseInt(state.rows[index].id, 10) === id) {
					return state.rows[index];
				}
			}
			return null;
		}

		function openEditor(id) {
			var row = findRow(id);
			if (!row || !modal) {
				return;
			}

			state.editingId = id;
			if (editSource) {
				editSource.textContent = String(row.source_text || '').replace(/\s+/g, ' ').trim().substring(0, 240);
			}
			if (editText) {
				editText.value = row.translated_text || '';
			}
			setStatus(editStatus, '', false);
			modal.hidden = false;
			if (editText) {
				editText.focus();
			}
		}

		function closeEditor() {
			if (modal) {
				modal.hidden = true;
			}
			state.editingId = 0;
		}

		function saveEditor() {
			if (!state.editingId || !editText) {
				return;
			}

			var textValue = editText.value;
			if (String(textValue).trim() === '') {
				setStatus(editStatus, text('saveFailed', 'The translation could not be saved.'), true);
				return;
			}

			editSave.disabled = true;
			setStatus(editStatus, text('saving', 'Saving translation…'), false);
			restRequest('/translations/' + state.editingId + '/update', {
				method: 'POST',
				body: { translated_text: textValue }
			}).then(function () {
				closeEditor();
				loadTranslations();
				loadSummary();
			}).catch(function (error) {
				setStatus(editStatus, error.message, true);
			}).then(function () {
				editSave.disabled = false;
			});
		}

		function retryRow(id) {
			setStatus(statusNode, text('processing', 'Processing…'), false);
			restRequest('/translations/' + id + '/retry', { method: 'POST' }).then(function () {
				setStatus(statusNode, text('retried', 'Translation queued for retry.'), false);
				loadTranslations();
				loadSummary();
			}).catch(function (error) {
				setStatus(statusNode, error.message, true);
			});
		}

		function queueSelected() {
			var ids = selectedRows();
			if (ids.length === 0) {
				setStatus(statusNode, text('selectRows', 'Select at least one translation.'), true);
				return;
			}

			var groups = {};
			ids.forEach(function (id) {
				var row = findRow(id);
				if (!row || !row.source_text) {
					return;
				}
				var key = String(row.source_language || 'en') + '|' + String(row.target_language || '');
				if (!groups[key]) {
					groups[key] = { source: row.source_language || 'en', target: row.target_language, items: [] };
				}
				groups[key].items.push({
					text: row.source_text,
					source_type: row.source_type,
					source_id: row.source_id,
					field: row.field
				});
			});

			var keys = Object.keys(groups);
			if (keys.length === 0) {
				setStatus(statusNode, text('selectRows', 'Select at least one translation.'), true);
				return;
			}

			var pending = keys.length;
			var failed = false;
			setStatus(statusNode, text('processing', 'Processing…'), false);
			keys.forEach(function (key) {
				var group = groups[key];
				restRequest('/translations/bulk', {
					method: 'POST',
					body: {
						source_lang: group.source,
						target_lang: group.target,
						items: group.items
					}
				}).catch(function (error) {
					failed = true;
					setStatus(statusNode, error.message, true);
				}).then(function () {
					pending--;
					if (pending === 0) {
						if (!failed) {
							setStatus(statusNode, text('queued', 'Translations queued.'), false);
						}
						loadTranslations();
						loadSummary();
					}
				});
			});
		}

		function retrySelected() {
			var ids = selectedRows();
			if (ids.length === 0) {
				setStatus(statusNode, text('selectRows', 'Select at least one translation.'), true);
				return;
			}

			bulkApply.disabled = true;
			setStatus(statusNode, text('processing', 'Processing…'), false);
			restRequest('/translations/bulk-retry', {
				method: 'POST',
				body: { ids: ids }
			}).then(function (payload) {
				var errors = Array.isArray(payload.errors) ? payload.errors : [];
				setStatus(statusNode, errors.length ? errors.join(' ') : text('bulkRetried', 'Selected translations queued for retry.'), errors.length > 0);
				loadTranslations();
				loadSummary();
			}).catch(function (error) {
				setStatus(statusNode, error.message, true);
			}).then(function () {
				bulkApply.disabled = false;
			});
		}

		if (filterForm) {
			filterForm.addEventListener('submit', function (event) {
				event.preventDefault();
				state.page = 1;
				loadTranslations();
			});
		}

		if (rowsNode) {
			rowsNode.addEventListener('click', function (event) {
				var target = event.target;
				if (!target || !target.getAttribute) {
					return;
				}
				var editId = target.getAttribute('data-eurocross-edit');
				var retryId = target.getAttribute('data-eurocross-retry');
				if (editId) {
					openEditor(parseInt(editId, 10));
				} else if (retryId) {
					retryRow(parseInt(retryId, 10));
				}
			});
			rowsNode.addEventListener('change', updateSelectedCount);
		}

		if (selectAll) {
			selectAll.addEventListener('change', function () {
				Array.prototype.forEach.call(root.querySelectorAll('[data-eurocross-row-check]'), function (checkbox) {
					checkbox.checked = selectAll.checked;
				});
				updateSelectedCount();
			});
		}

		if (prevButton) {
			prevButton.addEventListener('click', function () {
				if (state.page > 1) {
					state.page--;
					loadTranslations();
				}
			});
		}

		if (nextButton) {
			nextButton.addEventListener('click', function () {
				if (state.page < state.lastPage) {
					state.page++;
					loadTranslations();
				}
			});
		}

		if (bulkApply) {
			bulkApply.addEventListener('click', function () {
				var action = bulkAction ? bulkAction.value : '';
				if (!action) {
					setStatus(statusNode, text('selectAction', 'Choose a bulk action first.'), true);
					return;
				}
				if (action === 'retry') {
					retrySelected();
				} else if (action === 'queue') {
					queueSelected();
				}
			});
		}

		Array.prototype.forEach.call(root.querySelectorAll('[data-eurocross-edit-close]'), function (button) {
			button.addEventListener('click', closeEditor);
		});
		if (editSave) {
			editSave.addEventListener('click', saveEditor);
		}

		loadSummary();
		loadTranslations();
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
		setupTranslations();
		setupDashboardRefresh();
	});
}());
