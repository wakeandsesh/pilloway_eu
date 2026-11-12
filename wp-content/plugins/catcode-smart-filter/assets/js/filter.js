/**
 * CatCode Smart Filter — storefront behaviour (WooCommerce).
 *
 * The filter is plain links first. This script upgrades them: a click fetches the
 * page the link points at, swaps the product grid and the filter block out of the
 * response, and pushes the URL into history. Nothing is rendered client-side, so
 * the AJAX view and the crawled view are the same HTML — the usual failure mode
 * of JSON-rendered filters (a sidebar Google never sees) cannot happen here.
 *
 * No dependencies, no jQuery, no CDN: it has to survive a locked-down CSP and a
 * theme that loads its own conflicting library versions.
 */
(function () {
	'use strict';

	var root = document.getElementById('ccf-filter');

	if (!root || root.dataset.ccBound === '1') {
		return;
	}

	root.dataset.ccBound = '1';

	var useAjax = root.dataset.ajax === '1';
	var autoApply = root.dataset.autoApply === '1';

	// ---------------------------------------------------------------- helpers

	function selectors(value) {
		return (value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
	}

	function busy(on) {
		root.classList.toggle('is-busy', !!on);

		selectors(root.dataset.container).forEach(function (selector) {
			document.querySelectorAll(selector).forEach(function (node) {
				node.classList.toggle('ccf-loading', !!on);
			});
		});
	}

	/**
	 * Outermost matches of a selector, in document order. A match nested inside
	 * another match is dropped: the outer swap replaces it anyway.
	 */
	function matches(scope, selector) {
		const all = Array.prototype.slice.call(scope.querySelectorAll(selector));

		return all.filter(function (node) {
			return !all.some(function (other) {
				return other !== node && other.contains(node);
			});
		});
	}

	/**
	 * Swap every match of one selector from the fetched document, pairing them by
	 * position. Themes print some blocks twice — Storefront repeats the result
	 * count and the pagination under the grid — and every copy has to follow.
	 *
	 * The product grid keeps its element and takes the new content. The extra
	 * blocks are replaced whole: WooCommerce re-writes the first result count two
	 * seconds after page load from a copy it took at load time, and on an
	 * element of its own that timer would put the old count back over a filter
	 * applied in those two seconds.
	 */
	function swap(doc, selector, replace) {
		let fresh;
		let live;

		try {
			fresh = matches(doc, selector);
			live = matches(document, selector);
		} catch (error) {
			return false; // An invalid selector in the settings.
		}

		live.forEach(function (node, index) {
			const next = fresh[index];

			if (!next) {
				// A copy the fresh page no longer has (pagination on a single page
				// of results, say) is emptied rather than left showing the previous
				// result. The element stays, so a later result can fill it again.
				node.innerHTML = '';
			} else if (replace) {
				node.replaceWith(document.importNode(next, true));
			} else {
				node.innerHTML = next.innerHTML;
			}
		});

		return live.length > 0;
	}

	/** Every outermost match of a list of selectors; invalid selectors are skipped. */
	function nodesOf(scope, list) {
		let out = [];

		list.forEach(function (selector) {
			try {
				out = out.concat(matches(scope, selector));
			} catch (error) {
				// An invalid selector in the settings.
			}
		});

		return out;
	}

	/** Nearest element that contains all the given nodes. */
	function commonAncestor(nodes) {
		let node = nodes.length ? nodes[0] : null;

		nodes.forEach(function (other) {
			while (node && !node.contains(other)) {
				node = node.parentElement;
			}
		});

		return node;
	}

	/**
	 * A selector that finds the same element in another copy of the page: the
	 * nearest ancestor with an id, then tag positions down from it.
	 */
	function pathOf(node) {
		const steps = [];

		while (node && node.nodeType === 1 && node.tagName !== 'BODY' && node.tagName !== 'HTML') {
			if (node.id) {
				steps.unshift('#' + CSS.escape(node.id));

				return steps.join(' > ');
			}

			let index = 1;
			let sibling = node.previousElementSibling;

			while (sibling) {
				if (sibling.tagName === node.tagName) {
					index++;
				}

				sibling = sibling.previousElementSibling;
			}

			steps.unshift(node.tagName.toLowerCase() + ':nth-of-type(' + index + ')');
			node = node.parentElement;
		}

		steps.unshift('body');

		return steps.join(' > ');
	}

	function find(scope, path) {
		try {
			return scope.querySelector(path);
		} catch (error) {
			return null;
		}
	}

	/**
	 * Swap the whole area that holds the grid and its companions. Used when the
	 * fresh page has a different set of blocks than the live one: pagination
	 * that appears only once there is more than one page, or an empty result
	 * where WooCommerce prints a "no products" notice instead of the grid. A
	 * block that does not exist on the live page has nowhere to be swapped into,
	 * so the area around them is replaced instead.
	 *
	 * The filter block and the sorting form survive as the same elements:
	 * handlers are bound to them.
	 */
	function swapRegion(doc, list) {
		let region = commonAncestor(nodesOf(document, list));
		const freshArea = commonAncestor(nodesOf(doc, list));
		const mirror = freshArea ? find(document, pathOf(freshArea)) : null;

		if (mirror) {
			region = region ? commonAncestor([region, mirror]) : mirror;
		}

		if (!region || region === document.body || region === document.documentElement) {
			return false;
		}

		const fresh = find(doc, pathOf(region));

		if (!fresh || fresh.tagName !== region.tagName || fresh.className !== region.className) {
			return false;
		}

		const next = document.importNode(fresh, true);

		if (region.contains(root)) {
			const slot = next.querySelector('#ccf-filter');

			if (!slot) {
				return false;
			}

			slot.replaceWith(root);
		}

		const forms = matches(region, 'form.woocommerce-ordering');

		matches(next, 'form.woocommerce-ordering').forEach(function (form, index) {
			if (forms[index]) {
				forms[index].innerHTML = form.innerHTML;
				form.replaceWith(forms[index]);
			} else {
				form.dataset.ccfFresh = '1'; // No WooCommerce handler; see below.
			}
		});

		region.replaceWith(next);

		return true;
	}

	function scrollToList() {
		var first = selectors(root.dataset.container)[0];
		var node = first ? document.querySelector(first) : null;

		if (!node) {
			return;
		}

		var top = node.getBoundingClientRect().top + window.pageYOffset - 90;

		if (window.pageYOffset > top) {
			window.scrollTo({ top: top, behavior: 'smooth' });
		}
	}

	function go(url, push) {
		if (!useAjax) {
			window.location.href = url;

			return;
		}

		busy(true);

		fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}

				return response.text();
			})
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');

				var swapped = false;
				const containers = selectors(root.dataset.container);
				const extras = selectors(root.dataset.containerExtra);
				const blocks = containers.concat(extras);

				// Block by block when both pages have the same set of blocks, the
				// surrounding area otherwise (see swapRegion).
				const same = blocks.every(function (selector) {
					try {
						return matches(doc, selector).length === matches(document, selector).length;
					} catch (error) {
						return true;
					}
				});

				if (same) {
					containers.forEach(function (selector) {
						swapped = swap(doc, selector) || swapped;
					});

					extras.forEach(function (selector) {
						swap(doc, selector, true);
					});

					// The sorting form carries the current query string in hidden
					// fields. Left as it was, changing the sort order after a filter
					// would drop the filter. Only its content is swapped: WooCommerce
					// binds the change handler to the form element itself.
					swap(doc, 'form.woocommerce-ordering', false);
				} else {
					swapped = swapRegion(doc, blocks);
				}

				var freshFilter = doc.getElementById('ccf-filter');

				if (freshFilter) {
					root.innerHTML = freshFilter.innerHTML;
					root.dataset.ccBound = '';
					bind();
				}

				if (!swapped) {
					// The theme does not have the configured container. Falling back
					// to a full load is better than a filter that appears to do
					// nothing — and the admin diagnostics flag this case.
					window.location.href = url;

					return;
				}

				if (push) {
					window.history.pushState({ ccFilter: 1 }, '', url);
				}

				document.title = doc.title || document.title;

				scrollToList();

				busy(false);
			})
			.catch(function () {
				window.location.href = url;
			});
	}

	// ------------------------------------------------------------------- bind

	function bind() {
		root.dataset.ccBound = '1';

		root.querySelectorAll('[data-ccf-link]').forEach(function (link) {
			link.addEventListener('click', function (event) {
				event.preventDefault();

				var url = link.getAttribute('href');

				if (!url || link.closest('.is-disabled')) {
					return;
				}

				if (autoApply) {
					go(url, true);
				} else {
					stage(url);
				}
			});
		});

		root.querySelectorAll('[data-ccf-select]').forEach(function (select) {
			select.addEventListener('change', function () {
				if (select.value) {
					autoApply ? go(select.value, true) : stage(select.value);
				}
			});
		});

		root.querySelectorAll('[data-ccf-collapse]').forEach(function (button) {
			button.addEventListener('click', function () {
				var facet = button.closest('.ccf__facet');

				if (facet) {
					facet.classList.toggle('is-collapsed');
				}
			});
		});

		root.querySelectorAll('[data-ccf-more]').forEach(function (button) {
			button.addEventListener('click', function () {
				var facet = button.closest('.ccf__facet');

				if (!facet) {
					return;
				}

				var expanded = facet.classList.toggle('is-expanded');

				button.textContent = expanded ? button.dataset.less : button.dataset.more;
			});
		});

		root.querySelectorAll('[data-ccf-search]').forEach(function (input) {
			input.addEventListener('input', function () {
				var needle = input.value.trim().toLowerCase();
				var facet = input.closest('.ccf__facet');

				if (!facet) {
					return;
				}

				facet.classList.add('is-expanded');

				facet.querySelectorAll('.ccf__value').forEach(function (item) {
					var label = (item.querySelector('.ccf__label') || {}).textContent || '';

					item.hidden = needle !== '' && label.toLowerCase().indexOf(needle) === -1;
				});
			});
		});

		root.querySelectorAll('[data-ccf-drawer]').forEach(function (button) {
			button.addEventListener('click', function () {
				root.classList.toggle('is-open');
			});
		});

		root.querySelectorAll('[data-ccf-range]').forEach(bindRange);

		applyVisibleLimits();
	}

	/** Values beyond the "show more" limit are hidden with a data attribute so the
	 *  CSS can do the work — no inline styles for a CSP to trip over. */
	function applyVisibleLimits() {
		root.querySelectorAll('[data-ccf-values]').forEach(function (list) {
			var limit = parseInt(list.dataset.visible || '0', 10);

			if (!limit) {
				return;
			}

			Array.prototype.forEach.call(list.children, function (item, index) {
				item.classList.toggle('ccf__value--extra', index >= limit);
			});
		});
	}

	// ------------------------------------------------------------------ range

	function bindRange(box) {
		var from = box.querySelector('[data-ccf-range-from]');
		var to = box.querySelector('[data-ccf-range-to]');
		var sliderFrom = box.querySelector('[data-ccf-slider-from]');
		var sliderTo = box.querySelector('[data-ccf-slider-to]');
		var fill = box.querySelector('[data-ccf-fill]');

		var min = parseFloat(box.dataset.min);
		var max = parseFloat(box.dataset.max);

		function clamp(value) {
			value = parseFloat(String(value).replace(',', '.'));

			if (isNaN(value)) {
				return min;
			}

			return Math.min(max, Math.max(min, value));
		}

		function paint() {
			if (!fill || max <= min) {
				return;
			}

			var a = (clamp(sliderFrom.value) - min) / (max - min) * 100;
			var b = (clamp(sliderTo.value) - min) / (max - min) * 100;

			fill.style.left = Math.min(a, b) + '%';
			fill.style.width = Math.abs(b - a) + '%';
		}

		function url() {
			var a = clamp(from.value);
			var b = clamp(to.value);

			if (a > b) {
				var swapped = a; a = b; b = swapped;
			}

			var base = box.dataset.base;
			var part = box.dataset.key + ':' + trim(a) + '-' + trim(b);

			var joiner = base.indexOf('?') === -1 ? '?' : '&';

			// The whole selection already lives in the base URL; the range part is
			// merged into it so a slider move never drops the other facets.
			if (base.indexOf('ccf=') !== -1) {
				return base.replace(/ccf=([^&]*)/, function (all, current) {
					var kept = decodeURIComponent(current)
						.split(';')
						.filter(function (chunk) { return chunk && chunk.split(':')[0] !== box.dataset.key; });

					kept.push(part);

					return 'ccf=' + kept.join(';').replace(/%/g, '%25').replace(/ /g, '%20');
				});
			}

			return base + joiner + 'ccf=' + part.replace(/%/g, '%25').replace(/ /g, '%20');
		}

		function trim(value) {
			return String(parseFloat(value.toFixed(4)));
		}

		function sync(source) {
			if (source === 'slider') {
				from.value = trim(clamp(sliderFrom.value));
				to.value = trim(clamp(sliderTo.value));
			} else {
				sliderFrom.value = clamp(from.value);
				sliderTo.value = clamp(to.value);
			}

			paint();
		}

		[sliderFrom, sliderTo].forEach(function (slider) {
			slider.addEventListener('input', function () { sync('slider'); });
			slider.addEventListener('change', function () { sync('slider'); commit(); });
		});

		[from, to].forEach(function (input) {
			input.addEventListener('change', function () { sync('input'); commit(); });
			input.addEventListener('keydown', function (event) {
				if (event.key === 'Enter') {
					event.preventDefault();
					sync('input');
					commit();
				}
			});
		});

		function commit() {
			var target = url();

			autoApply ? go(target, true) : stage(target);
		}

		paint();
	}

	// ------------------------------------------------------- manual apply mode

	var staged = null;

	function stage(url) {
		staged = url;

		var apply = root.querySelector('[data-ccf-apply]');

		if (!apply) {
			go(url, true);

			return;
		}

		apply.hidden = false;
		apply.setAttribute('href', url);

		var countUrl = root.dataset.countUrl;

		if (countUrl) {
			var params = url.split('?')[1] || '';

			fetch(countUrl + (countUrl.indexOf('?') === -1 ? '?' : '&') + params, { credentials: 'same-origin' })
				.then(function (response) { return response.json(); })
				.then(function (data) {
					var total = document.querySelector('[data-ccf-total]');

					if (total && typeof data.total === 'number') {
						apply.dataset.total = data.total;
						apply.textContent = apply.textContent.replace(/\s*\(\d+\)$/, '') + ' (' + data.total + ')';
					}
				})
				.catch(function () { /* the button still applies the selection */ });
		}

		apply.onclick = function (event) {
			event.preventDefault();

			if (staged) {
				go(staged, true);
			}
		};
	}

	// A sorting form that first appeared after a filter (the previous result was
	// empty) has no WooCommerce change handler, which is bound once on load.
	document.addEventListener('change', function (event) {
		const select = event.target;
		const form = select && select.closest ? select.closest('form.woocommerce-ordering') : null;

		if (form && form.dataset.ccfFresh === '1' && select.matches('select.orderby')) {
			form.submit();
		}
	});

	window.addEventListener('popstate', function () {
		if (useAjax) {
			go(window.location.href, false);
		}
	});

	bind();
})();
