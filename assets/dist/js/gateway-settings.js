/**
 * Beam Checkout gateway settings page.
 *
 * The Mode select switches between the live and test key sets. Both sets stay saved:
 * the inactive set is only hidden, so its values are still posted and never wiped.
 *
 * The form may be rendered after this script runs (or re-rendered later), so the state is
 * applied on load and again whenever the DOM changes, and the change handler is delegated.
 */
(function ($) {
	'use strict';

	var data = window.beamCheckoutSettings || {};
	var prefix = '#woocommerce_' + data.id + '_';
	var modeSelector = prefix + 'is_test';

	function buildNotice(type, text) {
		return $('<div class="beam-mode-notice"></div>')
			.attr('data-beam-notice', type)
			.css({
				background: type === 'test' ? '#fff8e5' : '#f0f6fc',
				border: '1px solid ' + (type === 'test' ? '#dba617' : '#72aee6'),
				borderLeftWidth: '4px',
				margin: '10px 0 0',
				padding: '10px 12px',
				maxWidth: '560px',
				lineHeight: 1.5,
			})
			.append($('<strong></strong>').text(type === 'test' && data.testTitle ? data.testTitle + ' ' : ''))
			.append(document.createTextNode(text || ''))
			.hide();
	}

	function sync() {
		var $mode = $(modeSelector);

		if (!$mode.length) {
			return;
		}

		var isTest = $mode.val() === 'yes';
		var $fieldset = $mode.closest('fieldset');

		if (!$fieldset.find('[data-beam-notice]').length) {
			$fieldset.append(buildNotice('test', data.testNotice), buildNotice('live', data.liveNotice));
		}

		$(prefix + 'api_key').add(prefix + 'private_key').closest('tr').toggle(!isTest);
		$(prefix + 'test_api_key').add(prefix + 'test_private_key').closest('tr').toggle(isTest);

		$fieldset.find('[data-beam-notice="test"]').toggle(isTest);
		$fieldset.find('[data-beam-notice="live"]').toggle(!isTest);
	}

	$(document).on('change', modeSelector, sync);

	$(sync);
	$(window).on('load pageshow', sync);

	if (window.MutationObserver) {
		var queued = false;

		new MutationObserver(function () {
			if (queued) {
				return;
			}

			queued = true;
			window.setTimeout(function () {
				queued = false;
				sync();
			}, 50);
		}).observe(document.body || document.documentElement, { childList: true, subtree: true });
	}
})(jQuery);
