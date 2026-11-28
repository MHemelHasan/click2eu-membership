(function () {
	'use strict';
	document.addEventListener('click', function (event) {
		var open = event.target.closest('[data-c2eu-dialog]');
		if (open) {
			var dialog = document.getElementById(open.getAttribute('data-c2eu-dialog'));
			if (dialog && typeof dialog.showModal === 'function') {
				dialog.showModal();
				dialog.querySelector('input[name="c2eu_contact_subject"]').focus();
			}
			return;
		}
		var close = event.target.closest('.c2eu-resume-contact__close');
		if (close) {
			close.closest('dialog').close();
		}
	});
	document.addEventListener('cancel', function (event) {
		if (event.target.matches('.c2eu-resume-contact__dialog')) {
			event.target.close();
		}
	}, true);
})();
