(function($){
	function fetchRows(page, query){
		$('.drm-loading').show();
		$.post(DRM_Admin.ajax_url, {
			action: 'drm_fetch',
			nonce: DRM_Admin.nonce,
			page: page || 1,
			s: query || ''
		}).done(function(resp){
			if(resp && resp.success){
				$('#the-list').html(resp.data.rows_html || '');
				$('.tablenav-pages').html(resp.data.pagination_html || '');
				$('.drm-count strong').text(resp.data.count_today || 0);
				$('.drm-today-date').text('— ' + (resp.data.date_label || ''));
				var isEmpty = !resp.data.rows_html;
				$('.drm-empty-state').toggle(isEmpty);
			} else {
				$('#the-list').empty();
				$('.drm-empty-state').show();
			}
		}).always(function(){
			$('.drm-loading').hide();
		});
	}

	function currentPage(){
		var cur = $('.tablenav-pages .current').text();
		cur = parseInt(cur, 10);
		return isNaN(cur) ? 1 : cur;
	}

	$(document).on('click', '.drm-refresh', function(){
		fetchRows(currentPage(), $('#drm-search-input').val());
	});

	// Pagination click handling.
	$(document).on('click', '.tablenav-pages .page-numbers', function(e){
		e.preventDefault();
		var page = $(this).text();
		page = parseInt(page, 10);
		if(!isNaN(page)){
			fetchRows(page, $('#drm-search-input').val());
		}
	});

	// Search submit handling.
	$('.drm-search-form').on('submit', function(e){
		e.preventDefault();
		fetchRows(1, $('#drm-search-input').val());
	});

	// Verify action.
	$(document).on('click', '.drm-verify', function(){
		if(!confirm(DRM_Admin.i18n.confirmVerify)) return;
		var btn = $(this);
		btn.prop('disabled', true);
		$.post(DRM_Admin.ajax_url, {
			action: 'drm_verify_member',
			nonce: DRM_Admin.nonce,
			user_id: btn.data('user-id')
		}).done(function(resp){
			if(resp && resp.success){
				fetchRows(currentPage(), $('#drm-search-input').val());
			}
		}).always(function(){
			btn.prop('disabled', false);
		});
	});

	// Auto-refresh every 5 minutes.
	setInterval(function(){
		fetchRows(currentPage(), $('#drm-search-input').val());
	}, 5 * 60 * 1000);
})(jQuery);

