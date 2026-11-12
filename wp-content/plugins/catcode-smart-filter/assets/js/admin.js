/**
 * Admin behaviour: tabs, facet table, batched index rebuild, diagnostics.
 *
 * No dependencies — every call is JSON in, JSON out through admin-ajax with the
 * nonce the page was rendered with.
 */
(function () {
	'use strict';

	var cfg = window.catcodeSfAdmin || {};
	var i18n = cfg.i18n || {};

	function notice(message, type) {
		var box = document.getElementById('ccf-notice');

		if (!box) {
			return;
		}

		box.innerHTML = '<div class="notice notice-' + (type || 'success') + ' is-dismissible"><p></p></div>';
		box.querySelector('p').textContent = message;

		window.scrollTo({ top: 0, behavior: 'smooth' });
	}

	function post(action, payload) {
		var url = cfg.ajax + '?action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(cfg.nonce);

		return fetch(url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify(payload || {})
		}).then(function (r) { return r.json(); });
	}

	function get(action, params) {
		var url = cfg.ajax + '?action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(cfg.nonce) + (params || '');

		return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}

	function report(res) {
		if (!res || !res.success) {
			notice((res && res.data && res.data.message) || i18n.failed, 'error');

			return false;
		}

		if (res.data && res.data.message) {
			notice(res.data.message, 'success');
		}

		return true;
	}

	// ---------------------------------------------------------------- tabs

	document.querySelectorAll('.ccf-tabs .nav-tab').forEach(function (tab) {
		tab.addEventListener('click', function (event) {
			event.preventDefault();

			document.querySelectorAll('.ccf-tabs .nav-tab').forEach(function (t) { t.classList.remove('nav-tab-active'); });
			document.querySelectorAll('.ccf-pane').forEach(function (p) { p.hidden = true; });

			tab.classList.add('nav-tab-active');

			var pane = document.querySelector(tab.getAttribute('href'));

			if (pane) {
				pane.hidden = false;
			}
		});
	});

	// ------------------------------------------------------------ settings

	var saveBtn = document.getElementById('ccf-save');

	if (saveBtn) {
		saveBtn.addEventListener('click', function () {
			var form = document.getElementById('ccf-form');
			var payload = {};

			form.querySelectorAll('input, select').forEach(function (field) {
				if (!field.name) {
					return;
				}

				// An unticked checkbox has to send "no" explicitly, otherwise the
				// saved value keeps whatever it was before.
				payload[field.name] = 'checkbox' === field.type ? (field.checked ? 'yes' : 'no') : field.value;
			});

			post('catcode_sf_save', payload).then(report);
		});
	}

	// -------------------------------------------------------------- facets

	var facetRows = document.getElementById('ccf-facet-rows');
	var facetData = [];

	function widgetSelect(selected) {
		return ['checkbox', 'radio', 'select', 'range', 'swatch_color', 'swatch_image', 'toggle'].map(function (w) {
			return '<option value="' + w + '"' + (w === selected ? ' selected' : '') + '>' + w + '</option>';
		}).join('');
	}

	function loadFacets() {
		if (!facetRows) {
			return;
		}

		get('catcode_sf_facets').then(function (res) {
			if (!res.success) {
				return;
			}

			facetData = res.data.facets || [];

			facetRows.innerHTML = facetData.map(function (f, i) {
				return '<tr data-i="' + i + '">' +
					'<td><input type="text" data-field="label" value="' + escapeAttr(f.label) + '"></td>' +
					'<td><code>' + escapeHtml(f.source_key) + '</code></td>' +
					'<td><select data-field="widget">' + widgetSelect(f.widget) + '</select></td>' +
					'<td><select data-field="logic"><option value="or"' + ('or' === f.logic ? ' selected' : '') + '>OR</option><option value="and"' + ('and' === f.logic ? ' selected' : '') + '>AND</option></select></td>' +
					'<td><code>' + escapeHtml(f.facet_key) + '</code></td>' +
					'<td><button type="button" class="button-link" data-values="' + i + '">' + escapeHtml(i18n.valueCol || '') + ': ' + f.values.length + '</button></td>' +
					'<td><input type="number" style="width:5rem" data-field="sort_order" value="' + parseInt(f.sort_order, 10) + '"></td>' +
					'<td><input type="checkbox" data-field="status"' + (f.status ? ' checked' : '') + '></td>' +
					'<td><button type="button" class="button-link-delete" data-del="' + f.facet_id + '">&times;</button></td>' +
				'</tr>';
			}).join('') || '<tr><td colspan="9">—</td></tr>';

			facetRows.querySelectorAll('[data-del]').forEach(function (b) {
				b.addEventListener('click', function () {
					post('catcode_sf_delete_facet', { facet_id: parseInt(b.dataset.del, 10) }).then(function (res) {
						if (report(res)) { loadFacets(); }
					});
				});
			});

			facetRows.querySelectorAll('[data-values]').forEach(function (b) {
				b.addEventListener('click', function () { renderValues(parseInt(b.dataset.values, 10)); });
			});
		});
	}

	function escapeHtml(v) {
		var d = document.createElement('div');
		d.textContent = null === v || undefined === v ? '' : String(v);
		return d.innerHTML;
	}

	function escapeAttr(v) {
		return escapeHtml(v).replace(/"/g, '&quot;');
	}

	function renderValues(index) {
		var facet = facetData[index];
		var box = document.getElementById('ccf-values');

		if (!facet || !box) {
			return;
		}

		var options = facet.values.map(function (v) {
			return '<option value="' + v.value_id + '">' + escapeHtml(v.label) + '</option>';
		}).join('');

		box.innerHTML = '<h3>' + escapeHtml(facet.label) + '</h3><table class="widefat striped"><thead><tr>' +
			'<th>' + escapeHtml(i18n.valueCol || '') + '</th><th>slug</th><th>№</th><th>#hex</th><th>' + escapeHtml(i18n.merge || '') + '</th>' +
			'</tr></thead><tbody>' +
			facet.values.map(function (v, vi) {
				return '<tr data-v="' + vi + '">' +
					'<td><input type="text" data-vfield="label" value="' + escapeAttr(v.label) + '"></td>' +
					'<td><code>' + escapeHtml(v.slug) + '</code></td>' +
					'<td><input type="text" style="width:6rem" data-vfield="num_value" value="' + escapeAttr(v.num_value) + '"></td>' +
					'<td><input type="text" style="width:6rem" data-vfield="color" value="' + escapeAttr(v.color) + '"></td>' +
					'<td><select data-merge="' + v.value_id + '"><option value="">—</option>' + options + '</select></td>' +
				'</tr>';
			}).join('') +
			'</tbody></table>';

		box.querySelectorAll('[data-vfield]').forEach(function (input) {
			input.addEventListener('change', function () {
				var vi = parseInt(input.closest('tr').dataset.v, 10);
				facet.values[vi][input.dataset.vfield] = input.value;
			});
		});

		box.querySelectorAll('[data-merge]').forEach(function (select) {
			select.addEventListener('change', function () {
				if (!select.value) {
					return;
				}

				post('catcode_sf_merge_values', { from: parseInt(select.dataset.merge, 10), into: parseInt(select.value, 10) })
					.then(function (res) {
						if (report(res)) { loadFacets(); box.innerHTML = ''; }
					});
			});
		});
	}

	var saveFacets = document.getElementById('ccf-save-facets');

	if (saveFacets) {
		saveFacets.addEventListener('click', function () {
			var rows = [];

			facetRows.querySelectorAll('tr[data-i]').forEach(function (tr) {
				var facet = facetData[parseInt(tr.dataset.i, 10)];

				if (!facet) {
					return;
				}

				var row = { facet_id: facet.facet_id, values: facet.values };

				tr.querySelectorAll('[data-field]').forEach(function (field) {
					row[field.dataset.field] = 'checkbox' === field.type ? (field.checked ? 1 : 0) : field.value;
				});

				rows.push(row);
			});

			post('catcode_sf_save_facets', { facets: rows }).then(function (res) {
				if (report(res)) { loadFacets(); }
			});
		});
	}

	// -------------------------------------------------------------- import

	var discover = document.getElementById('ccf-discover');

	if (discover) {
		discover.addEventListener('click', function () {
			var box = document.getElementById('ccf-import');

			box.hidden = false;

			get('catcode_sf_discover').then(function (res) {
				if (!res.success) {
					return;
				}

				var sources = res.data.sources || {};
				var html = '';

				['attribute', 'taxonomy'].forEach(function (kind) {
					(sources[kind] || []).forEach(function (s) {
						if (!s.products) {
							return;
						}

						html += '<label style="display:block">' +
							'<input type="checkbox" value="' + escapeAttr(s.source_key) + '" data-kind="' + kind + '"' + '> ' +
							escapeHtml(s.label) + ' <span class="description">— ' + escapeHtml(i18n.products || '') + ': ' + s.products + ' / ' + s.terms + '</span>' +
							(s.imported ? ' ✓' : '') +
						'</label>';
					});
				});

				box.innerHTML = html + '<p><button type="button" class="button button-primary" id="ccf-import-run">OK</button></p>';

				document.getElementById('ccf-import-run').addEventListener('click', function () {
					var chosen = [];

					box.querySelectorAll('input:checked').forEach(function (input) {
						chosen.push({ source: input.dataset.kind, source_key: input.value, widget: 'checkbox' });
					});

					if (!chosen.length) {
						return;
					}

					post('catcode_sf_import', { sources: chosen }).then(function (r) {
						if (report(r)) {
							box.hidden = true;
							loadFacets();

							// New values carry no index rows yet: build them right away
							// instead of leaving the owner to find the button.
							var rebuildButton = document.getElementById('ccf-rebuild');

							if (rebuildButton && parseInt(r.data.values, 10) > 0) {
								rebuildButton.click();
							}
						}
					});
				});
			});
		});
	}

	// --------------------------------------------------------------- index

	var rebuild = document.getElementById('ccf-rebuild');

	if (rebuild) {
		rebuild.addEventListener('click', function () {
			var progress = document.getElementById('ccf-progress');
			var bar = progress.querySelector('progress');
			var label = progress.querySelector('span');

			progress.hidden = false;
			rebuild.disabled = true;

			post('catcode_sf_index_start', {}).then(function (start) {
				if (!report(start)) {
					rebuild.disabled = false;

					return;
				}

				var total = parseInt(start.data.total, 10) || 0;
				var done = 0;

				function step(cursor) {
					post('catcode_sf_index_step', { cursor: cursor }).then(function (res) {
						if (!res.success) {
							report(res);
							rebuild.disabled = false;

							return;
						}

						done += parseInt(res.data.processed, 10) || 0;

						bar.value = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 100;
						label.textContent = i18n.indexing + ' ' + done + ' / ' + total;

						document.getElementById('ccf-stat-rows').textContent = res.data.rows_total;

						var built = document.getElementById('ccf-stat-built');

						if (built && res.data.done && res.data.built_at) {
							built.textContent = res.data.built_at;
						}

						if (res.data.done) {
							rebuild.disabled = false;
							label.textContent = i18n.done;
							notice(i18n.done, 'success');

							return;
						}

						step(res.data.cursor);
					});
				}

				step(0);
			});
		});
	}

	var prune = document.getElementById('ccf-prune');

	if (prune) {
		prune.addEventListener('click', function () { post('catcode_sf_prune', {}).then(report); });
	}

	// --------------------------------------------------------- diagnostics

	var check = document.getElementById('ccf-check');

	if (check) {
		check.addEventListener('click', function () {
			get('catcode_sf_diagnostics').then(function (res) {
				if (!res.success) {
					return;
				}

				var d = res.data;

				var labels = i18n.diag || {};

				var rows = [
					['index_rows', d.index_rows],
					['products', d.products],
					['facets', d.facets],
					['orphans', d.orphans],
					['unindexed', d.unindexed],
					['empty_values', d.empty_values],
					['lookup_table', d.lookup_table ? labels.ok : labels.missing],
					['version', d.version]
				];

				var html = '<table class="widefat striped"><tbody>';

				rows.forEach(function (row) {
					html += '<tr><td>' + (labels[row[0]] || row[0]) + '</td><td>' + row[1] + '</td></tr>';
				});

				document.getElementById('ccf-diag-out').innerHTML = html + '</tbody></table>';
			});
		});
	}

	var drop = document.getElementById('ccf-drop');

	if (drop) {
		drop.addEventListener('click', function () {
			if (!window.confirm(i18n.confirm)) {
				return;
			}

			post('catcode_sf_drop_data', {}).then(function (res) {
				if (report(res)) { loadFacets(); }
			});
		});
	}

	loadFacets();
})();
