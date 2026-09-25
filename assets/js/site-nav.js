/* Site Menu hamburger. Toggles .is-open and aria-expanded. No jQuery. */
(function () {
	'use strict';

	function breakpoint(nav) {
		var bp = parseInt(nav.getAttribute('data-breakpoint'), 10);
		if (!bp || bp < 320) bp = 782;
		return bp;
	}

	function mobile(nav) {
		if (!window.matchMedia) return false;
		return window.matchMedia('(max-width: ' + breakpoint(nav) + 'px)').matches;
	}

	function dropdown(nav) {
		return nav.classList.contains('lb-site-nav--dropdown');
	}

	function sync(nav) {
		var btn = nav.querySelector('.lb-site-nav__toggle');
		if (!btn) return;
		var isMobile = mobile(nav);
		nav.classList.toggle('is-mobile', isMobile);
		if (!isMobile && !dropdown(nav)) {
			nav.classList.remove('is-open');
			btn.setAttribute('aria-expanded', 'false');
		}
	}

	function syncAll() {
		var nodes = document.querySelectorAll('.lb-site-nav');
		for (var i = 0; i < nodes.length; i++) sync(nodes[i]);
	}

	document.addEventListener('click', function (event) {
		var btn = event.target && event.target.closest ? event.target.closest('.lb-site-nav__toggle') : null;
		if (!btn) return;
		var nav = btn.closest('.lb-site-nav');
		if (!nav || (!mobile(nav) && !dropdown(nav))) return;
		var open = !nav.classList.contains('is-open');
		nav.classList.toggle('is-open', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') return;
		var nodes = document.querySelectorAll('.lb-site-nav.is-open');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].classList.remove('is-open');
			var btn = nodes[i].querySelector('.lb-site-nav__toggle');
			if (!btn) continue;
			btn.setAttribute('aria-expanded', 'false');
			btn.focus();
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', syncAll);
	} else {
		syncAll();
	}
	window.addEventListener('resize', syncAll);
})();
