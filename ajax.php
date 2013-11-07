<?php

add_action('wp_ajax_md_do_apply_changes', 'md_handle_apply_changes_request');
function md_handle_apply_changes_request(){

	$nonce = $_POST['_wpnonce'];
	if(!wp_verify_nonce( $nonce, 'md_do_apply_changes_nonce')){
		die;
	}

	$md_post_id = $_POST['md_post_id'];
	$user_id = get_current_user_id();

	md_apply_changes($md_post_id, $user_id);

	$metadraft = md_get_metadraft($md_post_id);

	$post_type = get_post_type($metadraft->md_post_id);
	$post_type_object = get_post_type_object($post_type);
	$post_type_label = $post_type_object->labels->singular_name;

	if($metadraft->md_status == 'md-closed-applied' && $metadraft->src_post_id){

		$permalink = get_permalink($metadraft->src_post_id);

		if($metadraft->md_is_orig){
			$msg = "<p class='md_notification success'><span class='md_success'>" . $post_type_label . " published!</span> <a href='" . $permalink . "'>View&nbsp;" . strtolower($post_type_label) . "&nbsp;&raquo;</a></p>";
		} else {
			$msg = "<p class='md_notification success'><span class='md_success'>Changes applied!</span> <a href='" . $permalink . "'>View&nbsp;" . strtolower($post_type_label) . "&nbsp;&raquo;</a></p>";
		}

		$return = json_encode(array('status'=>'success', 'message'=>$msg));
		echo $return;

	} else {

		if($metadraft->md_is_orig){
			$msg = "<p class='md_notification error'><span class='md_error'>Error:</span> " . $post_type_label . " could not be published. Please try again later.";
		} else {
			$msg = "<p class='md_notification error'><span class='md_error'>Error:</span> " . $post_type_label . " could not be updated. Please try again later.";
		}

		$return = json_encode(array('status'=>'error', 'message'=>$msg));
		echo $return;

	}

	die;
}