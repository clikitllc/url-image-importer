(function ($) {
	'use strict';

	$('.uimptr-notice').on('click', 'button', function (e) {
		e.preventDefault();

		const $notice = $(this).closest('.uimptr-notice');
		const noticeId = $notice.data('notice-id');
		const action = $(this).data('action');
		const link = $(this).data('link');

		$.ajax({
			url: uimptrPromo.ajaxurl,
			type: 'POST',
			data: {
				action: 'uimptr_handle_promo_action',
				notice_id: noticeId,
				action_type: action,
				nonce: uimptrPromo.nonce
			},
			success: function (response) {
				if (response.success) {
					$notice.slideUp();

					if (link) {
						window.open(link, '_blank');
						return;
					}
				}
			}
		});
	});
})(jQuery);