(function () {
	'use strict';

	function setOpen(root, open) {
		var button = root.querySelector('.pilloway-language-switcher__button');
		var menu = root.querySelector('.pilloway-language-switcher__menu');
		if (!button || !menu) return;
		root.classList.toggle('is-open', open);
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		menu.hidden = !open;
	}

	document.querySelectorAll('[data-pilloway-language-switcher]').forEach(function (root) {
		var button = root.querySelector('.pilloway-language-switcher__button');
		if (!button) return;

		button.addEventListener('click', function () {
			setOpen(root, button.getAttribute('aria-expanded') !== 'true');
		});

		root.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				setOpen(root, false);
				button.focus();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) setOpen(root, false);
		});
	});
}());

/* Desktop category popover: hover, click and keyboard all keep disclosure state. */
(function () {
	'use strict';

	var desktopHover = window.matchMedia('(min-width: 600px) and (hover: hover)');

	document.querySelectorAll('.pilloway-category-disclosure').forEach(function (details) {
		var summary = details.querySelector('summary');
		var categoryPanel = details.querySelector('.pilloway-nav-categories-list');
		if (!summary) return;

		var pointerInside = false;
		var dismissed = false;

		function syncExpanded() {
			summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
			if (categoryPanel) categoryPanel.hidden = !details.open;
		}

		function positionPanel() {
			if (!desktopHover.matches || !details.open || !categoryPanel) return;

			categoryPanel.style.setProperty('left', '0px', 'important');
			var panelWidth = categoryPanel.getBoundingClientRect().width;
			var detailsLeft = details.getBoundingClientRect().left;
			var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
			var gutter = 12;
			var minOffset = gutter - detailsLeft;
			var maxOffset = viewportWidth - gutter - panelWidth - detailsLeft;
			var offset = Math.max(minOffset, Math.min(0, maxOffset));

			categoryPanel.style.setProperty('left', offset + 'px', 'important');
		}

		function clearPanelPosition() {
			if (categoryPanel) categoryPanel.style.removeProperty('left');
		}

		function closeOnViewportModeChange() {
			pointerInside = false;
			dismissed = false;
			details.open = false;
			syncExpanded();
			clearPanelPosition();
		}

		if (typeof desktopHover.addEventListener === 'function') {
			desktopHover.addEventListener('change', closeOnViewportModeChange);
		} else if (typeof desktopHover.addListener === 'function') {
			desktopHover.addListener(closeOnViewportModeChange);
		}

		function schedulePanelPosition() {
			window.requestAnimationFrame(positionPanel);
		}

		syncExpanded();
		details.addEventListener('toggle', function () {
			syncExpanded();
			if (details.open) schedulePanelPosition();
			else clearPanelPosition();
		});

		window.addEventListener('resize', function () {
			if (desktopHover.matches && details.open) positionPanel();
			else clearPanelPosition();
		});

		details.addEventListener('pointerenter', function (event) {
			if (!desktopHover.matches || event.pointerType === 'touch') return;
			dismissed = false;
			pointerInside = true;
			details.open = true;
			syncExpanded();
			schedulePanelPosition();
		});

		details.addEventListener('pointerleave', function () {
			if (!desktopHover.matches) return;
			pointerInside = false;
			if (!details.contains(document.activeElement)) {
				details.open = false;
				dismissed = false;
				syncExpanded();
				clearPanelPosition();
			}
		});

		details.addEventListener('focusin', function () {
			if (desktopHover.matches && !dismissed) {
				details.open = true;
				syncExpanded();
				schedulePanelPosition();
			}
		});

		details.addEventListener('focusout', function (event) {
			if (!desktopHover.matches || details.contains(event.relatedTarget) || pointerInside) return;
			details.open = false;
			dismissed = false;
			syncExpanded();
			clearPanelPosition();
		});

		summary.addEventListener('click', function (event) {
			if (desktopHover.matches && pointerInside) {
				event.preventDefault();
				details.open = true;
				syncExpanded();
				schedulePanelPosition();
			} else {
				dismissed = false;
			}
		});

		details.addEventListener('keydown', function (event) {
			if (event.key !== 'Escape' || !details.open) return;
			event.preventDefault();
			dismissed = true;
			details.open = false;
			syncExpanded();
			clearPanelPosition();
			summary.focus();
		});
	});
}());
