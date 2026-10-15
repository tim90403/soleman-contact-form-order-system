(function () {
	'use strict';

	if (typeof scfosAdmin === 'undefined') {
		return;
	}

	var cfg = scfosAdmin;
	if (!Array.isArray(cfg.displayFields) || !cfg.displayFields.length) {
		cfg.displayFields = [
			{ key: 'course_name', label: '課程名稱' },
			{ key: 'course_fee', label: '課程費用' },
			{ key: 'bank_code', label: '匯款末5碼' }
		];
	}
	var cache = {};
	var detailFetching = {};
	var fetchTimer = null;
	var observer = null;

	document.body.classList.add('scfos-submissions-page');

	function showToast(message, type) {
		var el = document.querySelector('.scfos-toast');
		if (!el) {
			el = document.createElement('div');
			el.className = 'scfos-toast';
			document.body.appendChild(el);
		}
		el.textContent = message;
		el.className = 'scfos-toast is-visible ' + (type === 'error' ? 'is-error' : 'is-success');
		window.clearTimeout(showToast._t);
		showToast._t = window.setTimeout(function () {
			el.classList.remove('is-visible');
		}, 3500);
	}

	function apiGet(path) {
		return fetch(cfg.restUrl + path, {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				Accept: 'application/json'
			}
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) || cfg.errorGeneric;
					throw new Error(msg);
				}
				return data;
			});
		});
	}

	function apiPost(path) {
		return fetch(cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce,
				Accept: 'application/json',
				'Content-Type': 'application/json'
			},
			body: '{}'
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg = (data && data.message) || cfg.errorGeneric;
					throw new Error(msg);
				}
				return data;
			});
		});
	}

	function createPaymentButton(id, isConfirmed) {
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'scfos-payment-btn ' + (isConfirmed ? 'is-confirmed' : 'is-pending');
		btn.dataset.submissionId = String(id);
		btn.textContent = isConfirmed ? cfg.confirmedText : cfg.confirmText;
		btn.addEventListener('click', onPaymentClick);
		return btn;
	}

	function updateButton(btn, isConfirmed) {
		btn.disabled = false;
		btn.classList.toggle('is-confirmed', !!isConfirmed);
		btn.classList.toggle('is-pending', !isConfirmed);
		btn.textContent = isConfirmed ? cfg.confirmedText : cfg.confirmText;
	}

	function onPaymentClick(e) {
		var btn = e.currentTarget;
		var id = btn.dataset.submissionId;
		if (!id || btn.disabled) {
			return;
		}

		btn.disabled = true;
		btn.textContent = cfg.loadingText;

		apiPost('submissions/' + id + '/payment')
			.then(function (data) {
				var confirmed = !!data.is_confirmed;
				if (!cache[id]) {
					cache[id] = {};
				}
				cache[id].is_confirmed = confirmed;
				updateButton(btn, confirmed);
				syncButtonsForId(id, confirmed);
				showToast(data.message || (confirmed ? cfg.confirmedText : cfg.confirmText), 'success');
			})
			.catch(function (err) {
				var wasConfirmed = !!(cache[id] && cache[id].is_confirmed);
				updateButton(btn, wasConfirmed);
				showToast(err.message || cfg.errorGeneric, 'error');
			});
	}

	function syncButtonsForId(id, confirmed) {
		document.querySelectorAll('.scfos-payment-btn[data-submission-id="' + id + '"]').forEach(function (btn) {
			updateButton(btn, confirmed);
		});
	}

	function getListTable() {
		return document.querySelector('.e-form-submissions-list-table');
	}

	function getDetailTable() {
		return document.querySelector('.e-form-submissions-item-table');
	}

	function extractListIds(table) {
		var ids = [];
		table.querySelectorAll('tbody tr').forEach(function (row) {
			if (row.classList.contains('no-items') || row.classList.contains('scfos-augmented-list')) {
				return;
			}
			var link = row.querySelector('a[href*="#/"]') || row.querySelector('a[href*="/"]');
			var id = null;
			if (link) {
				var href = link.getAttribute('href') || '';
				var match = href.match(/#\/(\d+)/) || href.match(/\/(\d+)\s*$/);
				if (match) {
					id = parseInt(match[1], 10);
				}
			}
			if (!id) {
				var checkbox = row.querySelector('input[type="checkbox"]');
				if (checkbox && checkbox.value && /^\d+$/.test(checkbox.value)) {
					id = parseInt(checkbox.value, 10);
				}
			}
			if (id) {
				row.dataset.scfosId = String(id);
				ids.push(id);
			}
		});
		return ids;
	}

	function fieldClass(key) {
		return 'scfos-field-' + key.replace(/[^a-z0-9_-]/gi, '');
	}

	function getFieldValue(item, key) {
		return (item && item.fields && item.fields[key]) || '';
	}

	function insertBeforeDate(row, node) {
		var dateCell = row.querySelector('.column-date');
		if (dateCell) {
			row.insertBefore(node, dateCell);
		} else {
			row.appendChild(node);
		}
	}

	function ensureHeaderRow(row) {
		if (!row || row.querySelector('.scfos-th-payment')) {
			return;
		}

		cfg.displayFields.forEach(function (field) {
			var th = document.createElement('th');
			th.className = 'manage-column scfos-th-field ' + fieldClass(field.key);
			th.textContent = field.label;
			insertBeforeDate(row, th);
		});

		var thPay = document.createElement('th');
		thPay.className = 'manage-column scfos-th-payment';
		thPay.textContent = cfg.paymentLabel;
		insertBeforeDate(row, thPay);
	}

	function ensureListHeaders(table) {
		ensureHeaderRow(table.querySelector('thead tr'));
		ensureHeaderRow(table.querySelector('tfoot tr'));
	}

	function ensureListCells(table, itemsMap) {
		table.querySelectorAll('tbody tr').forEach(function (row) {
			var id = parseInt(row.dataset.scfosId || '0', 10);
			if (!id) {
				return;
			}

			var item = itemsMap[id] || cache[id] || {
				fields: {},
				is_confirmed: false
			};

			var payCell = row.querySelector('.scfos-col-payment');

			if (!payCell) {
				cfg.displayFields.forEach(function (field) {
					var td = document.createElement('td');
					td.className = 'scfos-col-field ' + fieldClass(field.key);
					td.dataset.colname = field.label;
					insertBeforeDate(row, td);
				});

				payCell = document.createElement('td');
				payCell.className = 'scfos-col-payment';
				payCell.dataset.colname = cfg.paymentLabel;
				insertBeforeDate(row, payCell);
			}

			cfg.displayFields.forEach(function (field) {
				var cell = row.querySelector('td.' + fieldClass(field.key));
				if (cell) {
					cell.textContent = getFieldValue(item, field.key) || '—';
				}
			});

			var btn = payCell.querySelector('.scfos-payment-btn');
			if (!btn) {
				payCell.innerHTML = '';
				payCell.appendChild(createPaymentButton(id, !!item.is_confirmed));
			} else if (!btn.disabled) {
				updateButton(btn, !!item.is_confirmed);
			}
		});
	}

	function renameDetailFields(table, item) {
		var formLabels = (item && item.form_labels) || {};

		table.querySelectorAll('tbody tr').forEach(function (row) {
			if (row.dataset.scfosField || row.classList.contains('scfos-detail-payment-row')) {
				return;
			}
			var cells = row.querySelectorAll('td');
			if (cells.length < 2) {
				return;
			}
			var label = (cells[0].textContent || '').trim();

			cfg.displayFields.forEach(function (field) {
				if (row.dataset.scfosField) {
					return;
				}
				if (label === field.key || label === field.label || (formLabels[field.key] && label === formLabels[field.key])) {
					cells[0].textContent = field.label;
					row.dataset.scfosField = field.key;
				}
			});
		});
	}

	function ensureDetailFieldRows(table, item) {
		if (!item || !item.id) {
			return;
		}

		var body = table.querySelector('tbody') || table;

		cfg.displayFields.forEach(function (field) {
			var existing = body.querySelector('tr[data-scfos-field="' + field.key + '"]');
			if (existing) {
				if (existing.classList.contains('scfos-detail-field-row')) {
					var valueCell = existing.querySelector('td:last-child');
					if (valueCell) {
						valueCell.textContent = getFieldValue(item, field.key) || '—';
					}
				}
				return;
			}

			var tr = document.createElement('tr');
			tr.className = 'scfos-detail-field-row';
			tr.dataset.scfosField = field.key;

			var tdLabel = document.createElement('td');
			tdLabel.textContent = field.label;

			var tdValue = document.createElement('td');
			tdValue.textContent = getFieldValue(item, field.key) || '—';

			tr.appendChild(tdLabel);
			tr.appendChild(tdValue);

			var paymentRow = body.querySelector('.scfos-detail-payment-row');
			if (paymentRow) {
				body.insertBefore(tr, paymentRow);
			} else {
				body.appendChild(tr);
			}
		});
	}

	function ensureDetailPaymentRow(table, item) {
		if (!item || !item.id) {
			return;
		}

		var existing = table.querySelector('.scfos-detail-payment-row');
		if (existing) {
			var btn = existing.querySelector('.scfos-payment-btn');
			if (btn && !btn.disabled) {
				updateButton(btn, !!item.is_confirmed);
			}
			return;
		}

		var tr = document.createElement('tr');
		tr.className = 'scfos-detail-payment-row';

		var tdLabel = document.createElement('td');
		tdLabel.textContent = cfg.paymentLabel;

		var tdValue = document.createElement('td');
		tdValue.appendChild(createPaymentButton(item.id, !!item.is_confirmed));

		tr.appendChild(tdLabel);
		tr.appendChild(tdValue);

		var body = table.querySelector('tbody') || table;
		body.appendChild(tr);
	}

	function hideDetailMeta() {
		document.querySelectorAll('#misc-publishing-actions .misc-pub-section').forEach(function (section) {
			var text = (section.textContent || '').trim();
			if (/^Form:/i.test(text) || /^表單/.test(text) || /^Page:/i.test(text) || /^頁面/.test(text)) {
				section.classList.add('scfos-hide-form-section');
			}
		});

		var title = document.querySelector('.e-form-submissions-main__header h2');
		if (title && !title.dataset.scfosTitleCleaned) {
			title.dataset.scfosTitleCleaned = '1';
			title.textContent = title.textContent.replace(/#\s*\d+\s*$/, '').replace(/#\d+/, '').trim() || title.textContent;
		}
	}

	function getDetailIdFromLocation() {
		var hash = window.location.hash || '';
		var match = hash.match(/#\/(\d+)/);
		return match ? parseInt(match[1], 10) : 0;
	}

	function mergeItems(items) {
		(items || []).forEach(function (item) {
			cache[item.id] = item;
		});
	}

	function refresh() {
		var listTable = getListTable();
		var detailTable = getDetailTable();

		if (listTable) {
			ensureListHeaders(listTable);
			var ids = extractListIds(listTable);
			var missing = ids.filter(function (id) {
				return !cache[id];
			});

			if (missing.length) {
				apiGet('submissions?ids=' + missing.join(','))
					.then(function (data) {
						mergeItems(data.items || []);
						ensureListCells(listTable, cache);
					})
					.catch(function () {
						ensureListCells(listTable, cache);
					});
			} else {
				ensureListCells(listTable, cache);
			}
		}

		if (detailTable) {
			hideDetailMeta();

			var detailId = getDetailIdFromLocation();
			if (!detailId) {
				return;
			}

			var applyDetail = function (item) {
				renameDetailFields(detailTable, item);
				ensureDetailFieldRows(detailTable, item);
				ensureDetailPaymentRow(detailTable, item);
			};

			if (cache[detailId] && cache[detailId].form_labels) {
				applyDetail(cache[detailId]);
				return;
			}

			if (detailFetching[detailId]) {
				return;
			}
			detailFetching[detailId] = true;

			apiGet('submissions?with_labels=1&ids=' + detailId)
				.then(function (data) {
					mergeItems(data.items || []);
					if (cache[detailId]) {
						applyDetail(cache[detailId]);
					}
				})
				.catch(function () {
					/* ignore */
				})
				.then(function () {
					detailFetching[detailId] = false;
				});
		}
	}

	function scheduleRefresh() {
		window.clearTimeout(fetchTimer);
		fetchTimer = window.setTimeout(refresh, 120);
	}

	function startObserver() {
		var root = document.getElementById('e-form-submissions') || document.getElementById('wpbody-content');
		if (!root) {
			return;
		}

		observer = new MutationObserver(function () {
			scheduleRefresh();
		});

		observer.observe(root, {
			childList: true,
			subtree: true
		});

		scheduleRefresh();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', startObserver);
	} else {
		startObserver();
	}

	window.addEventListener('hashchange', scheduleRefresh);
})();
