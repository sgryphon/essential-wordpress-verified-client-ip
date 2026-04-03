(function () {
	var container = document.getElementById('vcip-schemes');
	if (!container) return;

	var schemeIndex = container.querySelectorAll('.vcip-scheme-panel').length;

	document.getElementById('vcip-add-scheme').addEventListener('click', function () {
		var tpl = window.vcipSchemeTemplate || '';
		tpl = tpl.replace(/__INDEX__/g, schemeIndex);
		var wrapper = document.createElement('div');
		wrapper.innerHTML = tpl;
		container.appendChild(wrapper.firstElementChild);
		schemeIndex++;
		reindex();
	});

	container.addEventListener('click', function (e) {
		var header = e.target.closest('.postbox-header');
		if (!header) return;
		if (e.target.closest('.vcip-scheme-controls')) return;

		var panel = header.closest('.vcip-scheme-panel');
		var inside = panel.querySelector('.inside');
		inside.style.display = inside.style.display === 'none' ? '' : 'none';
	});

	container.addEventListener('click', function (e) {
		var btn = e.target.closest('button');
		if (!btn) return;

		var panel = btn.closest('.vcip-scheme-panel');

		if (btn.classList.contains('vcip-move-up') && panel.previousElementSibling) {
			container.insertBefore(panel, panel.previousElementSibling);
			reindex();
		} else if (btn.classList.contains('vcip-move-down') && panel.nextElementSibling) {
			container.insertBefore(panel.nextElementSibling, panel);
			reindex();
		} else if (btn.classList.contains('vcip-delete-scheme')) {
			if (confirm(vcipI18n.deleteConfirm)) {
				panel.remove();
				reindex();
			}
		}
	});

	container.addEventListener('change', function (e) {
		if (e.target.classList.contains('vcip-enabled-checkbox')) {
			var header = e.target.closest('.postbox-header');
			if (header) {
				header.style.backgroundColor = e.target.checked ? '#f0f6fc' : '';
			}
		}
	});

	container.addEventListener('input', function (e) {
		if (e.target.classList.contains('vcip-scheme-name-input')) {
			var panel = e.target.closest('.vcip-scheme-panel');
			var title = panel.querySelector('.vcip-scheme-name');
			title.textContent = e.target.value || vcipI18n.newScheme;
		}
	});

	function reindex() {
		var panels = container.querySelectorAll('.vcip-scheme-panel');
		panels.forEach(function (panel, i) {
			panel.dataset.index = i;
			panel.querySelectorAll('[name]').forEach(function (el) {
				el.name = el.name.replace(/vcip_schemes\[\d+\]/, 'vcip_schemes[' + i + ']');
			});
		});
	}
})();