(function () {
	'use strict';

	document.querySelectorAll('.pilloway-variant-picker__dropdown').forEach(function (details) {
		var summary = details.querySelector('summary');
		var list = details.querySelector('.pilloway-variant-picker__list');
		if (!summary || !list) return;

		var animationFrame = 0;
		var autoScrolled = false;

		function syncExpanded() {
			summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
		}

		function clearPosition() {
			details.classList.remove('is-open-upwards');
			list.style.removeProperty('max-height');
		}

		function positionList() {
			animationFrame = 0;
			syncExpanded();

			if (!details.open) {
				clearPosition();
				return;
			}

			details.classList.remove('is-open-upwards');
			list.style.removeProperty('max-height');

			var trigger = summary.getBoundingClientRect();
			var visualViewport = window.visualViewport;
			var viewportTop = visualViewport ? visualViewport.offsetTop : 0;
			var viewportHeight = visualViewport ? visualViewport.height : (window.innerHeight || document.documentElement.clientHeight);
			var viewportBottom = viewportTop + viewportHeight;
			var configuredMax = parseFloat(window.getComputedStyle(list).maxHeight);
			var listHeight = Math.min(list.scrollHeight, Number.isFinite(configuredMax) ? configuredMax : list.scrollHeight);
			var gutter = 12;
			var spaceAbove = Math.max(0, trigger.top - viewportTop - gutter);
			var spaceBelow = Math.max(0, viewportBottom - trigger.bottom - gutter);
			var openUpwards = spaceBelow < listHeight && spaceAbove > spaceBelow;
			var availableSpace = openUpwards ? spaceAbove : spaceBelow;
			var largestSpace = Math.max(spaceAbove, spaceBelow);

			if (largestSpace < 96 && !autoScrolled && viewportHeight > 180) {
				autoScrolled = true;
				summary.scrollIntoView({ block: 'center', inline: 'nearest' });
				schedulePosition();
				return;
			}

			details.classList.toggle('is-open-upwards', openUpwards);

			if (availableSpace < listHeight) {
				list.style.maxHeight = Math.max(0, Math.floor(Math.min(listHeight, availableSpace))) + 'px';
			}
		}

		function schedulePosition() {
			if (animationFrame) window.cancelAnimationFrame(animationFrame);
			animationFrame = window.requestAnimationFrame(positionList);
		}

		syncExpanded();
		details.addEventListener('toggle', function () {
			syncExpanded();
			if (!details.open) {
				clearPosition();
				autoScrolled = false;
				return;
			}
			schedulePosition();
		});
		window.addEventListener('resize', schedulePosition, { passive: true });
		window.addEventListener('scroll', schedulePosition, { passive: true });
		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', schedulePosition, { passive: true });
			window.visualViewport.addEventListener('scroll', schedulePosition, { passive: true });
		}

		details.addEventListener('keydown', function (event) {
			if (event.key !== 'Escape' || !details.open) return;
			event.preventDefault();
			details.open = false;
			syncExpanded();
			clearPosition();
			autoScrolled = false;
			summary.focus();
		});

		document.addEventListener('click', function (event) {
			if (!details.open || details.contains(event.target)) return;
			details.open = false;
			syncExpanded();
			clearPosition();
			autoScrolled = false;
		});
	});
}());