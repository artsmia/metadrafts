jQuery(document).ready(function(){

	// Manage Metadrafts dashboard widget
	
	if(jQuery('#md_manage_metadrafts_widget ul.list').length){
		var md_manage_opts = {
		    valueNames: [ 'status', 'author', 'title', 'updated' ]
		};
		var manage_metadrafts_list = new List('md_manage_metadrafts_widget', md_manage_opts);
		jQuery('#md_manage_metadrafts_widget span.sort').on('click', function(){
			jQuery('#md_manage_metadrafts_widget span.sort').removeClass('current');
			jQuery(this).addClass('current');
		});
	}

	// My Metadrafts dashboard widget
	
	if(jQuery('#md_my_metadrafts_widget ul.list').length){
		var md_my_opts = {
		    valueNames: [ 'status', 'title', 'updated' ]
		};
		var my_metadrafts_list = new List('md_my_metadrafts_widget', md_my_opts);
		jQuery('#md_my_metadrafts_widget span.sort').on('click', function(){
			jQuery('#md_my_metadrafts_widget span.sort').removeClass('current');
			jQuery(this).addClass('current');
		});
	}

	// My Closed Metadrafts dashboard widget
	
	if(jQuery('#md_my_closed_metadrafts_widget ul.list').length){
		var md_my_closed_opts = {
		    valueNames: [ 'date_closed', 'status', 'title' ]
		};
		var my_metadrafts_list = new List('md_my_closed_metadrafts_widget', md_my_closed_opts);
		jQuery('#md_my_closed_metadrafts_widget span.sort').on('click', function(){
			jQuery('#md_my_closed_metadrafts_widget span.sort').removeClass('current');
			jQuery(this).addClass('current');
		});
	}

	// Metadrafts metabox

	jQuery('#md_toggle_request_review').on('click', function(e){
		e.preventDefault();
		jQuery('#md_request_review_form').slideDown(125);
	});
	jQuery('#md_toggle_apply_changes').on('click', function(e){
		e.preventDefault();
		jQuery('#md_apply_changes_form').slideDown(125);
	});
	jQuery('#md_post_status').on('change', function(e){
		if( 'future' === jQuery( this ).val() ){
			jQuery( '#md_schedule_date' ).slideDown(125);
		} else {
			jQuery( '#md_schedule_date' ).slideUp(125);
		}
	});

	// Apply changes from dashboard

	jQuery('a.md_ajax_apply').on('click', function(e){
		e.preventDefault();
		var md_post_id = jQuery(this).data('md-post-id');
		jQuery.post(
			ajaxurl,
			{
				action		:	'md_do_apply_changes',
				md_post_id	:	md_post_id,
				_wpnonce	:	mdAjax.applyChangesNonce
			},
			function(response)
			{
				var responseObject = jQuery.parseJSON(response);
				if(responseObject.status == 'success'){
					jQuery('#md_manage_metadrafts_widget li.metadrafts_list_item.' + md_post_id).html(responseObject.message);
				} else {
					jQuery('#md_manage_metadrafts_widget li.metadrafts_list_item.' + md_post_id + ' div.content').append(responseObject.message);
				}
			}
		);
	});

});