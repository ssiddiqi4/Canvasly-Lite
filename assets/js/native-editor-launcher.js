(function () {
	'use strict';
	try {
		var cfg = window.canvaslyLiteNativeEditor || {};
		var builderBase = cfg.url || '';
		var fixedId = parseInt(cfg.postId, 10) || 0;
		var label = cfg.label || 'Edit with Canvasly';
		var saving = false;

		function currentId() {
			try {
				if (fixedId) {
					return fixedId;
				}
				if (window.wp && wp.data && wp.data.select) {
					return parseInt(wp.data.select('core/editor').getCurrentPostId() || 0, 10) || 0;
				}
			} catch (e) {}
			return 0;
		}

		function openBuilder(id) {
			if (!id || !builderBase) {
				return;
			}
			window.location.href = builderBase + '&post_id=' + encodeURIComponent(id);
		}

		function saveAndOpen() {
			var id = currentId();
			if (id) {
				openBuilder(id);
				return;
			}
			if (saving) {
				return;
			}
			saving = true;
			try {
				if (window.wp && wp.data && wp.data.dispatch) {
					var editor = wp.data.dispatch('core/editor');
					if (editor && editor.savePost) {
						editor.savePost();
					}
				}
			} catch (e) {
				saving = false;
				return;
			}
			var tries = 0;
			var timer = setInterval(function () {
				tries++;
				var newId = currentId();
				if (newId) {
					clearInterval(timer);
					openBuilder(newId);
				} else if (tries > 80) {
					clearInterval(timer);
					saving = false;
				}
			}, 250);
		}

		function makeButton(className) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.id = 'lb-native-editor-button';
			btn.className = className;
			btn.textContent = label;
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				saveAndOpen();
			});
			return btn;
		}

		function toolbarTarget() {
			var selectors = [
				'.edit-post-header-toolbar',
				'.editor-document-tools',
				'.editor-header__toolbar',
				'.edit-post-header__toolbar',
				'.editor-header__settings',
				'.edit-post-header__settings',
				'.editor-header__left'
			];
			for (var i = 0; i < selectors.length; i++) {
				var node = document.querySelector(selectors[i]);
				if (node) {
					return node;
				}
			}
			return null;
		}

		function addButton() {
			var existing = document.getElementById('lb-native-editor-button');
			var target = toolbarTarget();
			if (target) {
				if (existing && existing.parentNode === target) {
					return;
				}
				var stray = document.querySelector('.lb-native-editor-launch');
				if (stray) {
					stray.remove();
				}
				target.appendChild(existing || makeButton('components-button is-primary lb-native-editor-button'));
				return;
			}
			if (existing) {
				return;
			}
			var heading = document.querySelector('.wp-heading-inline');
			if (heading && heading.parentNode) {
				heading.parentNode.insertBefore(makeButton('page-title-action lb-native-editor-button'), heading.nextSibling);
				return;
			}
			var canvas = document.querySelector('.editor-visual-editor, .edit-post-visual-editor, .interface-interface-skeleton__content');
			if (!canvas || !canvas.parentNode) {
				return;
			}
			var bar = document.createElement('div');
			bar.className = 'lb-native-editor-launch';
			bar.appendChild(makeButton('components-button is-primary is-compact lb-native-editor-button'));
			canvas.parentNode.insertBefore(bar, canvas);
		}

		if (window.wp && wp.domReady) {
			wp.domReady(addButton);
		}
		window.addEventListener('load', addButton);
		var tries = 0;
		var timer = setInterval(function () {
			tries++;
			addButton();
			if (document.getElementById('lb-native-editor-button') || tries > 120) {
				clearInterval(timer);
			}
		}, 250);
		if (window.MutationObserver && document.body) {
			var observer = new MutationObserver(function () {
				if (!document.getElementById('lb-native-editor-button')) {
					addButton();
				}
			});
			observer.observe(document.body, { childList: true, subtree: true });
		}
		if (window.wp && wp.data && wp.data.subscribe) {
			wp.data.subscribe(function () {
				if (!document.getElementById('lb-native-editor-button')) {
					addButton();
				}
			});
		}
	} catch (e) {}
})();
