(function () {
	document.querySelectorAll('.vcip-diag-row').forEach(function (row) {
		row.addEventListener('click', function () {
			var detail = document.getElementById('vcip-detail-' + row.dataset.index);
			detail.style.display = detail.style.display === 'none' ? '' : 'none';
		});
	});
})();