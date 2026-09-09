(function () {
	'use strict';

	if (typeof scfosAdmin === 'undefined') {
		return;
	}

	var cfg = scfosAdmin;
	var cache = {};
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

	function ensureListHeaders(table) {
		var headerRow = table.querySelector('thead tr');
		if (!headerRow || headerRow.querySelector('.scfos-th-bank-code')) {
			return;
		}

		var thBank = document.createElement('th');
		thBank.className = 'manage-column scfos-th-bank-code';
		thBank.textContent = cfg.bankCodeLabel;

		var thPay = document.createElement('th');
		thPay.className = 'manage-column scfos-th-payment';
		thPay.textContent = cfg.paymentLabel;

		var dateTh = headerRow.querySelector('.column-date');
		if (dateTh) {
			headerRow.insertBefore(thBank, dateTh);
			headerRow.insertBefore(thPay, dateTh);
		} else {
			headerRow.appendChild(thBank);
			headerRow.appendChild(thPay);
		}

		// Mirror footer if present.
		var footerRow = table.querySelector('tfoot tr');
		if (footerRow && !footerRow.querySelector('.scfos-th-bank-code')) {
			footerRow.insertBefore(thBank.cloneNode(true), footerRow.querySelector('.column-date') || null);
			var payClone = thPay.cloneNode(true);
			var dateFooter = footerRow.querySelector('.column-date');
			if (dateFooter) {
				footerRow.insertBefore(payClone, dateFooter);
			} else {
				footerRow.appendChild(payClone);
			}
		}
	}

	function ensureListCells(table, itemsMap) {
		table.querySelectorAll('tbody tr').forEach(function (row) {
			var id = parseInt(row.dataset.scfosId || '0', 10);
			if (!id) {
				return;
			}

			var item = itemsMap[id] || cache[id] || {
				bank_code: '',
				is_confirmed: false
			};

			var bankCell = row.querySelector('.scfos-col-bank-code');
			var payCell = row.querySelector('.scfos-col-payment');

			if (!bankCell) {
				bankCell = document.createElement('td');
				bankCell.className = 'scfos-col-bank-code';
				payCell = document.createElement('td');
				payCell.className = 'scfos-col-payment';

				var dateCell = row.querySelector('.column-date');
				if (dateCell) {
					row.insertBefore(bankCell, dateCell);
					row.insertBefore(payCell, dateCell);
				} else {
					row.appendChild(bankCell);
					row.appendChild(payCell);
				}
			}

			bankCell.textContent = item.bank_code || '—';

			var btn = payCell.querySelector('.scfos-payment-btn');
			if (!btn) {
				payCell.innerHTML = '';
				payCell.appendChild(createPaymentButton(id, !!item.is_confirmed));
			} else if (!btn.disabled) {
				updateButton(btn, !!item.is_confirmed);
			}
		});
	}

	function renameDetailBankCode(table, item) {
		table.querySelectorAll('tbody tr').forEach(function (row) {
			if (row.classList.contains('scfos-detail-payment-row') || row.classList.contains('scfos-detail-bank-row')) {
				return;
			}
			var cells = row.querySelectorAll('td');
			if (cells.length < 2) {
				return;
			}
			var label = (cells[0].textContent || '').trim();
			var value = (cells[1].textContent || '').trim();
			var isBankField =
				label === cfg.bankCodeField ||
				label === cfg.bankCodeLabel ||
				(item && item.bank_code !== '' && item.bank_code === value);

			if (isBankField) {
				cells[0].textContent = cfg.bankCodeLabel;
				row.classList.add('scfos-bank-code-row');
			}
		});
	}

	function ensureDetailBankRow(table, item) {
		if (!item || !item.id) {
			return;
		}

		var existing = table.querySelector('.scfos-detail-bank-row');
		if (existing) {
			var valueCell = existing.querySelector('td:last-child');
			if (valueCell) {
				valueCell.textContent = item.bank_code || '—';
			}
			return;
		}

		// If original bank_code row already exists and was renamed, skip injecting duplicate.
		if (table.querySelector('.scfos-bank-code-row')) {
			return;
		}

		var tr = document.createElement('tr');
		tr.className = 'scfos-detail-bank-row';

		var tdLabel = document.createElement('td');
		tdLabel.textContent = cfg.bankCodeLabel;

		var tdValue = document.createElement('td');
		tdValue.textContent = item.bank_code || '—';

		tr.appendChild(tdLabel);
		tr.appendChild(tdValue);

		var body = table.querySelector('tbody') || table;
		var paymentRow = body.querySelector('.scfos-detail-payment-row');
		if (paymentRow) {
			body.insertBefore(tr, paymentRow);
		} else {
			body.insertBefore(tr, body.firstChild);
		}
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
				renameDetailBankCode(detailTable, item);
				ensureDetailBankRow(detailTable, item);
				ensureDetailPaymentRow(detailTable, item);
			};

			if (cache[detailId]) {
				applyDetail(cache[detailId]);
				return;
			}

			apiGet('submissions?ids=' + detailId)
				.then(function (data) {
					mergeItems(data.items || []);
					if (cache[detailId]) {
						applyDetail(cache[detailId]);
					}
				})
				.catch(function () {
					/* ignore */
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
