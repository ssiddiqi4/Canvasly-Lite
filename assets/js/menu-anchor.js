/* Same-page anchor jumps. CSS scroll-margin-top clears a sticky header.
   scrollIntoView honors that margin; reduced-motion skips the animation. */
(function () {
	'use strict';

	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function targetFrom(link) {
		var url;
		try {
			url = new URL(link.href, window.location.href);
		} catch (err) {
			return null;
		}
		if (url.origin !== window.location.origin) return null;
		if (url.pathname !== window.location.pathname || url.search !== window.location.search) return null;
		if (!url.hash || url.hash === '#') return null;
		var id = decodeURIComponent(url.hash.slice(1));
		if (!id) return null;
		return document.getElementById(id);
	}

	document.addEventListener('click', function (event) {
		if (event.defaultPrevented || event.button !== 0) return;
		if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
		var link = event.target && event.target.closest ? event.target.closest('a[href*="#"]') : null;
		if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
		var el = targetFrom(link);
		if (!el) return;
		event.preventDefault();
		el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
		if (window.history && window.history.pushState) {
			window.history.pushState(null, '', '#' + el.id);
		}
	});
})();
