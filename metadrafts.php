<?php
/*
Plugin Name: Metadrafts
Description: Creates a curated workflow for the revision of published posts as well as the creation of new posts, including edits to postmeta
Version: 1.0
Author: Minneapolis Institute of Arts
*/

// TODO: Replace with option
date_default_timezone_set('America/Chicago');

include(plugin_dir_path(__FILE__) . 'utilities.php');
include(plugin_dir_path(__FILE__) . 'metaboxes.php');
include(plugin_dir_path(__FILE__) . 'widgets.php');
include(plugin_dir_path(__FILE__) . 'options.php');
include(plugin_dir_path(__FILE__) . 'ajax.php');

/*
 * MD_ENQUEUE_STYLE
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('admin_enqueue_scripts', 'md_enqueue_scripts_and_styles');
function md_enqueue_scripts_and_styles(){
	wp_enqueue_script('list_js', plugins_url('js/list.min.js', __FILE__), array(), false, true);
	wp_enqueue_script('md_admin_js', plugins_url('js/md_admin.js', __FILE__), array('jquery'), false, true);
	wp_localize_script('md_admin_js', 'mdAjax', array(
		applyChangesNonce => wp_create_nonce('md_do_apply_changes_nonce'),
	));
	wp_enqueue_style('md_admin_css', plugins_url('css/md_admin.css', __FILE__));
}

/*
 * MD_SETUP
 * Prepare system on plugin activation
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
register_activation_hook( __FILE__, 'md_setup' );
function md_setup(){
	
	// Create Metadrafts table
	global $wpdb;
	$md_metadrafts = $wpdb->prefix . "md_metadrafts";
	$wpdb->query("CREATE TABLE IF NOT EXISTS $md_metadrafts (
		`md_id` int(11) NOT NULL AUTO_INCREMENT,
		`md_post_id` int(11) NOT NULL,
		`src_post_id` int(11) DEFAULT NULL,
		`md_author_id` int(11) NOT NULL,
		`md_status` varchar(45) NOT NULL,
		`md_is_orig` int(1) NOT NULL,
		`md_closed_by` int(11) DEFAULT NULL,
		`md_date_closed` datetime DEFAULT NULL,
		PRIMARY KEY (`md_id`))");

	// Create Comments table
	$md_comments = $wpdb->prefix . "md_comments";
	$wpdb->query("CREATE TABLE IF NOT EXISTS $md_comments (
		`cmt_id` int(11) NOT NULL AUTO_INCREMENT,
		`cmt_date_posted` datetime NOT NULL,
		`cmt_author_id` int(11) NOT NULL,
		`md_id` int(11) NOT NULL,
		`cmt_content` longtext DEFAULT NULL,
		`cmt_type` varchar(45) NOT NULL,
		PRIMARY KEY (`cmt_id`))");

	// Create Comment Relationships table
	$md_comments_rel = $wpdb->prefix . "md_comments_rel";
	$wpdb->query("CREATE TABLE IF NOT EXISTS $md_comments_rel (
		`rel_id` int(11) NOT NULL AUTO_INCREMENT,
		`cmt_id` int(11) NOT NULL,
		`user_id` int(11) NOT NULL,
		`rel_date_seen` datetime NOT NULL,
		PRIMARY KEY (`rel_id`))");

	// Add bypass_metadrafts and manage_metadrafts capabilities to admin
	// From there a capability manager can be used to assign the
	// capabilities to other users
	$admin = get_role( 'administrator' );
	$admin->add_cap( 'bypass_metadrafts' );
	$admin->add_cap( 'manage_metadrafts' );
}


/*
 * MD_CLEANUP
 * Return system to normal following plugin deactivation
 * 
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

/* 
register_deactivation_hook( __FILE__, 'md_cleanup' );
function md_cleanup(){
	
	// Drop Metadrafts table
	global $wpdb;
	$md_metadrafts = $wpdb->prefix . "md_metadrafts";
	$wpdb->query("DROP TABLE IF EXISTS $md_metadrafts");
	
	// Drop Comments table
	$md_comments = $wpdb->prefix . "md_comments";
	$wpdb->query("DROP TABLE IF EXISTS $md_comments");
	
	// Drop Comments Relationships table
	$md_comments_rel = $wpdb->prefix . "md_comments_rel";
	$wpdb->query("DROP TABLE IF EXISTS $md_comments_rel");
	
	// Remove bypass_metadrafts and manage_metadrafts capabilities from admin
	$editor = get_role( 'administrator' );
	$editor->remove_cap( 'bypass_metadrafts' );
	$editor->remove_cap( 'manage_metadrafts' );
}
*/


/*
 * MD_ROUTE_USER
 * Redirect authors to their personal revision of a page,
 * or create the page if a revision does not yet exist.
 *
 * Note: nesting pre_get_posts action within admin_init action is 
 * intentional and REQUIRED in order to avoid a redirect loop when routing 
 * to a metadraft for an existing post.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('admin_init', 'md_enqueue_route_user');
function md_enqueue_route_user(){

	// Users with sufficient privileges are not routed anywhere
	if (current_user_can('manage_metadrafts') || current_user_can('bypass_metadrafts')){
		return;
	}

	global $pagenow;
	$user_id = get_current_user_id();

	// See note above
	add_action('pre_get_posts', 'md_route_user');
	function md_route_user(){

		global $pagenow;
		global $post;
		$user_id = get_current_user_id();
					
		// The user is trying to edit an existing post. 
		if ($pagenow == 'post.php') {				

			// Post being edited is eligible for metadrafts
			if($post->post_type != 'attachment' && !md_is_metadraft($post->ID)) {
		
				// Has the user already created a metadraft for this post?
				$md_post_id = md_get_metadraft_for($post->ID, $user_id);

				if(!$md_post_id){
				
					// The user has not already created a metadraft.
					// Let's start one!
					$md_post_id = md_build_metadraft($user_id, false, $post->ID);

				}

				// Send user on their way
				$md_edit_link = get_edit_post_link($md_post_id, '');
				wp_redirect($md_edit_link);
				exit;

			}
		} 
	}

	// The user is trying to create a new post.	
	if ($pagenow == 'post-new.php') {

		// Let's start one!
		$md_post_id = md_build_metadraft($user_id, true);

		// Send user on their way
		$md_edit_link = get_edit_post_link($md_post_id, '');
		wp_redirect($md_edit_link);
		exit;

	}
}


/*
 * MD_FILTER_PENDING
 * Show only new, unapplied metadrafts on post overview screens
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_filter('posts_where' , 'md_filter_pending');
function md_filter_pending($where) {
  global $pagenow;
	if(is_admin() && $pagenow == 'edit.php') {
    global $wpdb;
    $metadrafts = $wpdb->prefix . "md_metadrafts";
		$where .= " AND (post_status != 'pending' || (post_status = 'pending' AND ID IN (SELECT * FROM (SELECT b.md_post_id FROM $metadrafts AS b WHERE b.md_is_orig = 1 AND (b.md_status = 'md-draft' OR b.md_status = 'md-pending')) Alias)))";
	}
	return $where;
}


/*
 * MD_FILTER_PENDING_COUNT
 * Count only new, unapplied metadrafts on post overview screens
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('current_screen', 'md_filter_pending_count');
function md_filter_pending_count($current_screen){
  add_filter( 'views_' . $current_screen->id, 'md_filter_pending_counts', 10, 1);
  function md_filter_pending_counts($views){
    global $wpdb;
    global $post_type;
    $metadrafts = $wpdb->prefix . "md_metadrafts";
    $in_progress = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'pending' AND post_type = '" . $post_type . "' AND ID IN (SELECT * FROM (SELECT b.md_post_id FROM $metadrafts AS b WHERE b.md_is_orig = 1 AND (b.md_status = 'md-draft' OR b.md_status = 'md-pending')) Alias)");
    if($in_progress){
      $views['pending'] = preg_replace('/P.+<\/span>/', 'In Progress <span class="count">(' . $in_progress . ')</span>', $views['pending']);
    } else {
      unset($views['pending']);
    }
    return $views;
  }
}


/*
 * MD_FILTER_POST_STATE_LABELS
 * Replace status tag for metadrafts on post overview screens
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_filter('display_post_states', 'md_filter_post_state_labels', 10, 2);
function md_filter_post_state_labels($post_states, $post){
  if ( 'pending' == $post->post_status && 'pending' != $post_status ){
    $post_states['pending'] = _x('In Progress', 'post state');
  }
  return $post_states;
}


/*
 * MD_HIDE_EDIT_SLUG_BOX 
 * Hide the edit slug box on the post edit screen if viewing a metadraft.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('admin_head', 'md_hide_edit_slug_box');
function md_hide_edit_slug_box(){
  global $post;
  if('pending' == $post->post_status){
    ?>
    <style type="text/css">
    #edit-slug-box{ display: none; }
    </style>
    <?php
  }
}


/*
 * MD_ON_SAVE_UPDATE_STATUS
 * On save, check to see if a post is an auto-draft metadraft, and if so, 
 * upgrade it to draft status.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('save_post', 'md_on_save_update_status', 10);
function md_on_save_update_status($post_id){

	if(!$_POST['md_apply_changes']){

		$metadraft = md_get_metadraft($post_id);
		$user_id = get_current_user_id();

		if($metadraft && $metadraft->md_status == 'md-auto-draft'){

			md_update_status($post_id, 'md-draft');
			md_add_comment($post_id, $user_id, 'init');

		}
	}
}

/*
 * MD_ON_SAVE_APPLY_CHANGES
 * On save, check to see if "Apply Changes" has been pressed, and if so,
 * apply them.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('save_post', 'md_on_save_apply_changes');
function md_on_save_apply_changes($post_id){

	if ($_POST['md_apply_changes'] && md_is_metadraft($post_id)){

		$user_id = get_current_user_id();

		md_apply_changes($post_id, $user_id);
		md_add_comment($post_id, $user_id, 'closed-applied');

	}
}


/*
 * MD_ON_SAVE_REQUEST_REVIEW
 * On save, check to see if a review request has been made, and if so, 
 * update metadraft status. 
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('save_post', 'md_on_save_request_review');
function md_on_save_request_review($post_id){

	if($_POST['md_request_review'] && md_is_metadraft($post_id)){

		$metadraft = md_get_metadraft($post_id);
		$user_id = get_current_user_id();
		$comment_content = $_POST['md_comment_content'];

		if ($metadraft
		&& ($metadraft->md_status == 'md-auto-draft' 
		||  $metadraft->md_status == 'md-draft')){

			md_update_status($post_id, 'md-pending');
			md_add_comment($post_id, $user_id, 'review-request', $comment_content);

		}

	}
}


/*
 * MD_ON_SAVE_POST_COMMENT
 * On save, check to see if a comment has been posted and record it.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('save_post', 'md_on_save_post_comment', 11);
function md_on_save_post_comment($post_id){

	if($_POST['md_submit_comment'] && $_POST['md_submit_comment'] != '' && $_POST['md_general_comment_content'] != '' && md_is_metadraft($post_id)){

		$user_id = get_current_user_id();
		$comment_content = $_POST['md_general_comment_content'];

		md_add_comment($post_id, $user_id, 'general', $comment_content);

	}
}


/*
 * MD_ON_SAVE_TRASH_METADRAFT
 * On save, check to see if the user has requested to trash the metadraft
 * and if so, trash it.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
add_action('save_post', 'md_on_save_trash_metadraft');
function md_on_save_trash_metadraft($post_id){

	if(md_is_metadraft($post_id) && $_POST['md_trash_post']){

		$user_id = get_current_user_id();
		$metadraft = md_get_metadraft($post_id);

		if(substr($metadraft->md_status, 0, 6) != 'closed') {

			md_close_metadraft('trash', $post_id, $user_id);
			md_add_comment($post_id, $user_id, 'closed-trashed');

		}
	}
}