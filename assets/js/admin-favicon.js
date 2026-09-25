(function () {
	var href = window.canvaslyLiteAdminIcon || '';
	function apply() {
		var head = document.head;
		if (!head || !href) {
			return;
		}
		var nodes = head.querySelectorAll('link[rel="icon"],link[rel="shortcut icon"],link[rel="apple-touch-icon"]');
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].parentNode) {
				nodes[i].parentNode.removeChild(nodes[i]);
			}
		}
		var icon = document.createElement('link');
		icon.rel = 'icon';
		icon.type = 'image/svg+xml';
		icon.setAttribute('sizes', 'any');
		icon.href = href;
		head.appendChild(icon);
		var apple = document.createElement('link');
		apple.rel = 'apple-touch-icon';
		apple.href = href;
		head.appendChild(apple);
	}
	apply();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', apply);
	} else {
		setTimeout(apply, 0);
	}
})();
