(function () {
	function syncFlag(cb) {
		var flag = cb.parentNode.querySelector('.lb-unit-disabled-flag');
		if (flag) {
			flag.value = cb.checked ? '0' : '1';
		}
		var row = cb.closest('tr');
		if (row) {
			row.setAttribute('data-enabled', cb.checked ? '1' : '0');
		}
	}
	document.querySelectorAll('.lb-unit-enabled').forEach(function (cb) {
		cb.addEventListener('change', function () {
			syncFlag(cb);
		});
	});
	var all = document.getElementById('lb-units-toggle-visible');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('.lb-units-table tbody tr').forEach(function (row) {
				if (row.hidden) {
					return;
				}
				var cb = row.querySelector('.lb-unit-enabled');
				if (!cb || cb.disabled) {
					return;
				}
				cb.checked = all.checked;
				syncFlag(cb);
			});
		});
	}
	var filter = document.getElementById('lb-unit-filter');
	var search = document.getElementById('lb-unit-search');
	function apply() {
		var mode = filter ? filter.value : 'all';
		var q = search ? search.value.toLowerCase().trim() : '';
		document.querySelectorAll('.lb-units-table tbody tr[data-type]').forEach(function (row) {
			var show = true;
			if (mode === 'lite' || mode === 'pro') {
				show = row.getAttribute('data-source') === mode;
			} else if (mode === 'unused') {
				show = row.getAttribute('data-usage') === '0' && row.getAttribute('data-locked') !== '1';
			} else if (mode === 'disabled') {
				show = row.getAttribute('data-enabled') === '0';
			}
			if (show && q) {
				show = (row.getAttribute('data-search') || '').indexOf(q) !== -1;
			}
			row.hidden = !show;
		});
		document.querySelectorAll('.lb-unit-source-row').forEach(function (head) {
			var src = head.getAttribute('data-source');
			var any = false;
			document.querySelectorAll('.lb-units-table tbody tr[data-type]').forEach(function (row) {
				if (!row.hidden && row.getAttribute('data-source') === src) {
					any = true;
				}
			});
			head.hidden = !any;
		});
	}
	if (filter) {
		filter.addEventListener('change', apply);
	}
	if (search) {
		search.addEventListener('input', apply);
	}
	var unused = document.getElementById('lb-disable-unused');
	if (unused) {
		unused.addEventListener('click', function (e) {
			if (!window.confirm(unused.getAttribute('data-confirm') || '')) {
				e.preventDefault();
			}
		});
	}
})();
