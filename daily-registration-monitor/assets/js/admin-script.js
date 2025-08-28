(function($){
	var cache = {};
	var debounceTimer = null;

	function keyFor(page, query, status){
		return 'p:' + (page||1) + '|q:' + (query||'') + '|st:' + (status||'');
	}

	function setLoading(state){
		$('.drm-loading').toggle(!!state);
	}

	function fetchRows(page, query, status){
		page = page || 1;
		query = query || '';
		status = status || '';
		var k = keyFor(page, query, status);
		if (cache[k]){
			applyResponse(cache[k]);
			return;
		}
		setLoading(true);
		$.post(DRM_Admin.ajax_url, {
			action: 'drm_fetch',
			nonce: DRM_Admin.nonce,
			page: page,
			s: query,
			status: status
		}).done(function(resp){
			if(resp && resp.success){
				cache[k] = resp;
				applyResponse(resp);
			}else{
				$('#the-list').empty();
				$('.drm-empty-state').show();
			}
		}).always(function(){ setLoading(false); });
	}

	function applyResponse(resp){
		$('#the-list').html(resp.data.rows_html || '');
		$('.tablenav-pages').html(resp.data.pagination_html || '');
		$('.drm-count strong').text(resp.data.count_today || 0);
		$('.drm-today-date').text('— ' + (resp.data.date_label || ''));
		var isEmpty = !resp.data.rows_html;
		$('.drm-empty-state').toggle(isEmpty);
		lazyLoadAvatars();
	}

	function currentPage(){
		var cur = $('.tablenav-pages .current').text();
		cur = parseInt(cur, 10);
		return isNaN(cur) ? 1 : cur;
	}

	function lazyLoadAvatars(){
		$('img.drm-avatar[data-src]').each(function(){
			var img = $(this);
			var src = img.attr('data-src');
			if (src){ img.attr('src', src).removeAttr('data-src'); }
		});
	}

	function doSearchDebounced(){
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function(){
			fetchRows(1, $('#drm-search-input').val(), $('#drm-status-filter').val());
		}, 250);
	}

	// Initialize events
	$(document).on('click', '.drm-refresh', function(){
		fetchRows(currentPage(), $('#drm-search-input').val(), $('#drm-status-filter').val());
	});

	$(document).on('click', '.tablenav-pages .page-numbers', function(e){
		e.preventDefault();
		var page = $(this).text();
		page = parseInt(page, 10);
		if(!isNaN(page)){
			fetchRows(page, $('#drm-search-input').val(), $('#drm-status-filter').val());
		}
	});

	$('.drm-search-form').on('submit', function(e){
		e.preventDefault();
		fetchRows(1, $('#drm-search-input').val(), $('#drm-status-filter').val());
	});

	$('#drm-search-input').on('input', doSearchDebounced);
	$('#drm-status-filter').on('change', function(){
		fetchRows(1, $('#drm-search-input').val(), $('#drm-status-filter').val());
	});
	$('#drm-clear-search').on('click', function(){
		$('#drm-search-input').val('');
		$('#drm-status-filter').val('');
		fetchRows(1, '', '');
	});

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
				fetchRows(currentPage(), $('#drm-search-input').val(), $('#drm-status-filter').val());
			}
		}).always(function(){ btn.prop('disabled', false); });
	});

	// AJAX CSV export
	$('.drm-export-form').on('submit', function(e){
		e.preventDefault();
		setLoading(true);
		var fd = new FormData();
		fd.append('action','drm_export_csv');
		fd.append('nonce', DRM_Admin.nonce);
		$.ajax({
			url: DRM_Admin.ajax_url,
			type: 'POST',
			data: fd,
			contentType: false,
			processData: false
		}).done(function(resp){
			if (resp && resp.success){
				var a = document.createElement('a');
				a.href = 'data:text/csv;base64,' + resp.data.csv_base64;
				a.download = resp.data.filename || 'export.csv';
				document.body.appendChild(a); a.click(); document.body.removeChild(a);
			} else {
				alert(DRM_Admin.i18n.exportError);
			}
		}).always(function(){ setLoading(false); });
	});

	// Keyboard shortcut: Ctrl+F focuses search input
	$(document).on('keydown', function(e){
		if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f'){
			e.preventDefault();
			$('#drm-search-input').focus();
		}
	});

	// Auto-refresh every 5 minutes.
	setInterval(function(){
		fetchRows(currentPage(), $('#drm-search-input').val(), $('#drm-status-filter').val());
	}, 5 * 60 * 1000);

	// On load, enhance avatars if any queued
	$(document).ready(function(){ lazyLoadAvatars(); });
})(jQuery);

