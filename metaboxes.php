<?php

/*
 * MD_METABOXES
 * Add and remove the appropriate metaboxes
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

add_action('add_meta_boxes', 'md_enqueue_metaboxes');
function md_enqueue_metaboxes(){

	global $post;
	global $pagenow;

	if ($pagenow == 'post.php'){

		if (md_is_metadraft($post->ID)){

			// Remove standard controls
			remove_meta_box('submitdiv', $post->post_type, 'side');

			add_meta_box(
				'metadrafts', 
				'Draft', 
				'md_metadraft_metabox', 
				$post->post_type, 
				'side', 
				'high'
			);

			add_meta_box(
				'metadraft_comments',
				'Draft Comments',
				'md_metadraft_comments_metabox',
				$post->post_type,
				'side',
				'high'
			);

		}
	}
}

/*
 * MD_METADRAFT_METABOX
 * Provides a framework for building metabox on the metadraft edit screen
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_metadraft_metabox($post){

	$metadraft = md_get_metadraft($post->ID);
	$source = md_get_source($post->ID);

	echo "<div class='submitbox'>";

	// Provide a hook to attach metabox content in discrete chunks
	do_action('md_metadraft_metabox', $post, $metadraft, $source);

	echo "</div>";

}

add_action('md_metadraft_metabox', 'md_metadraft_metabox_minor', 1, 3);
function md_metadraft_metabox_minor($post, $metadraft, $source){

	if(substr($metadraft->md_status, 0, 9) != 'md-closed'){

	?>

	<div id='minor-publishing-actions'>

		<?php if (current_user_can('manage_metadrafts') || get_current_user_id() == $metadraft->md_author_id) { ?>

		<div id='save-action'>
			<input type='submit' name='md_save_changes' id='save-post' value='Save Changes' class='button'>
			<span class='spinner'></span>
		</div>

		<?php } ?>

		<div class='clear'></div>

	</div>
		
	<?php

	}
}

add_action('md_metadraft_metabox', 'md_metadraft_metabox_misc', 2, 3);
function md_metadraft_metabox_misc($post, $metadraft, $source) { 

	if(substr($metadraft->md_status, 0, 9) != 'md-closed'){

	?>

	<div id='misc-publishing-actions'>

		<div class='misc-pub-section'>

			<?php

			$post_type = get_post_type_object($post->post_type);
			$post_type_label = strtolower($post_type->labels->singular_name);

			$author = get_userdata($metadraft->md_author_id);
			$author_name = $author->display_name;

			$source_edit_link = get_edit_post_link($source->ID);

			$permalink = get_permalink($post->ID);
			if(!$metadraft->md_is_orig){
				$source_permalink = get_permalink($metadraft->src_post_id);
			}

			if ($metadraft->md_author_id == get_current_user_id()){

				if($metadraft->md_is_orig) {

					echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview</a></div>";
					echo "<p>You are drafting a new " . $post_type_label . ".";
					echo "<br class='clear' />";
				
				} else { 

					if (current_user_can('bypass_metadrafts') || current_user_can('manage_metadrafts')){

						echo "<p>This is your revision of <strong>&ldquo;<a href='" . $source_edit_link . "'>" . $source->post_title . ".</a>&rdquo;</strong></p>";
						echo "<div id='md_preview_wrap'>";
						echo "<div id='md_preview_original'><a class='button' href='" . $source_permalink . "' target='_blank'>Preview Original</a></div>";
						echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview Draft</a></div>";
						echo "<br class='clear' />";
						echo "</div>";

					} else {

						echo "<p>This is your revision of <strong>&ldquo;" . $source->post_title . ".&rdquo;</strong></p>";
						echo "<div id='md_preview_wrap'>";
						echo "<div id='md_preview_original'><a class='button' href='" . $source_permalink . "' target='_blank'>Preview Original</a></div>";
						echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview Draft</a></div>";
						echo "<br class='clear' />";
						echo "</div>";

					}

				}

			} else {

				if ($metadraft->md_is_orig) {

					echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview</a></div>";
					echo "<p>This new " . $post_type_label . " is being drafted by " . $author_name . ".</p>";
					echo "<br class='clear' />";

				} else {

					if (current_user_can('bypass_metadrafts') || current_user_can('manage_metadrafts')){

						echo "<p>This is " . $author_name . "'s revision of <strong>&ldquo;<a href='" . $source_edit_link . "'>" . $source->post_title . ".</a>&rdquo;</strong></p>";
						echo "<div id='md_preview_wrap'>";
						echo "<div id='md_preview_original'><a class='button' href='" . $source_permalink . "' target='_blank'>Preview Original</a></div>";
						echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview Draft</a></div>";
						echo "<br class='clear' />";
						echo "</div>";

					} else {

						echo "<p>This is " . $author_name . "'s revision of <strong>&ldquo;" . $source->post_title . ".&rdquo;</strong></p>";
						echo "<div id='md_preview_wrap'>";
						echo "<div id='md_preview_original'><a class='button' href='" . $source_permalink . "' target='_blank'>Preview Original</a></div>";
						echo "<div id='md_preview_draft'><a class='button' href='" . $permalink . "' target='_blank'>Preview Draft</a></div>";
						echo "<br class='clear' />";
						echo "</div>";

					}

				}
			} 

			?>

		</div>

		<div class='clear'></div>

	</div>

	<?php

	}
}

add_action('md_metadraft_metabox', 'md_metadraft_status', 3, 3);
function md_metadraft_status($post, $metadraft, $source){

	$status_slug = $metadraft->md_status;
	switch($status_slug){
		case 'md-auto-draft':
			$status = 'Not Yet Saved';
			break;
		case 'md-draft':
			$status = 'In Progress';
			break;
		case 'md-pending':
			$status = 'Pending Review';
			break;
		case 'md-closed-applied':
			if($metadraft->md_is_orig){
				$status = 'Published';
			} else {
				$status = 'Changed Applied';
			}
			break;
		case 'md-closed-trashed':
			$status = 'Discarded';
			break;
		default:
			$status = 'Unknown';
	}

	$status_class = substr($metadraft->md_status, 0, 9) == 'md-closed' ? 'closed' : 'active';

	?>

	<div id='metadraft_status' class='<?php echo $status_class; ?>'>
		<p>Status: <span class='status_box <?php echo $status_slug; ?>'></span> <span class='status_name'><?php echo $status; ?></span></p>
	</div>

	<?php
}

add_action('md_metadraft_metabox', 'md_metadraft_nav', 4, 3);
function md_metadraft_nav($post, $metadraft, $source){

	$post_type = get_post_type_object($post->post_type);
	$post_type_label = $post_type->labels->singular_name;
	
	?>

	<div id='metadraft-navigation'>

	<?php 

	$siblings = md_get_siblings($post->ID);

	if ($metadraft->src_post_id && !empty($siblings)){
		
	?>

		<div id='metadraft-siblings' class='metadraft-mb-section'>

			<p>Other active revisions of the same <?php echo strtolower($post_type_label); ?>:</p>

			<ul>

			<?php

			foreach($siblings as $sibling){

				$author = get_userdata($sibling->md_author_id);
				$author_name = $author->display_name;	

				$edit_link = get_edit_post_link($sibling->md_post_id);

				$last_updated = md_last_updated($sibling->md_post_id);

				$status = $sibling->md_status == 'md-draft' ? 'in progress' : ($sibling->md_status == 'md-pending' ? 'pending review' : 'status unknown');

				echo "<li><p><a href='" . $edit_link . "'><strong>" . $author_name . "</strong></a> (" . $status . ")<br />Last updated " . $last_updated . "</p></li>";

			}

			?>

			</ul>

		</div>

	<?php 

	} 

	?>

		<div class='clear'></div>

	</div>

	<?php

}

add_action('md_metadraft_metabox', 'md_metadraft_major', 5, 3);
function md_metadraft_major($post, $metadraft, $source){

	if(substr($metadraft->md_status, 0, 9) != 'md-closed' ){

		$post_type = get_post_type_object($post->post_type);
		$post_type_label = $post_type->labels->singular_name;

		$status_slug = $metadraft->md_status;

		// Limit major publishing options to managers and the metadraft author
		if (current_user_can('manage_metadrafts') || get_current_user_id()==$metadraft->md_author_id){

			?>

			<div id='major-publishing-actions'>

				<div id='delete-action'>

					<input type='submit' name='md_trash_post' id='md_trash_post' class="submitdelete deletion" value='Discard Draft' />

				</div>

					<?php if (current_user_can('manage_metadrafts') && $metadraft->md_is_orig) { ?>

						<div id='publishing-action'>
							<span class="spinner"></span>
							<input type='submit' name='md_apply_changes' id='md_apply_changes' value='Publish <?php echo $post_type_label; ?>' class='button button-primary button-large'>
						</div>

					<?php } else if (current_user_can('manage_metadrafts')) { ?>

						<div id='publishing-action'>
							<span class="spinner"></span>
							<input type='submit' name='md_apply_changes' id='md_apply_changes' value='Apply Changes' class='button button-primary button-large'>
						</div>

					<?php } else if ($metadraft->md_status=='md-auto-draft' || $metadraft->md_status=='md-draft') { ?>

						<div id='publishing-action'>
							<a href='#' id='md_toggle_request_review' class='button button-large button-primary'>Request Review ...</a>
						</div>
						<div id='md_request_review_form'>
							<?php if ($metadraft->md_is_orig){ ?>
							<textarea name='md_comment_content' id='md_request_review_comment_field' placeholder='Assist the reviewer with a one-sentence description of your new <?php echo strtolower($post_type_label); ?>.'></textarea>
							<?php } else { ?>
							<textarea name='md_comment_content' id='md_request_review_comment_field' placeholder='Assist the reviewer with a one-sentence description your edits to this <?php echo strtolower($post_type_label); ?>.'></textarea>
							<?php } ?>
							<span class="spinner"></span>
							<input type='submit' name='md_request_review' id='md_request_review' value='Submit Request' class='button button-primary button-large'>
						</div>

					<?php } ?>

				<div class='clear'></div>

			</div>

			<?php
		}
	}
}

add_action('post_submitbox_misc_actions', 'md_source_metabox_nav', 1);
function md_source_metabox_nav(){

	global $post;
	$post_type = get_post_type_object($post->post_type);
	$post_type_label = $post_type->labels->singular_name;
	
	?>

	<div id='metadrafts'>

	<?php 

	$children = md_get_children($post->ID);

	if (!empty($children)){
		
	?>

		<div class='metadraft-mb-section'>

			<p>Active revisions of this <?php echo strtolower($post_type_label); ?>:</p>

			<ul>

			<?php

			foreach($children as $child){

				$author = get_userdata($child->md_author_id);
				$author_name = $author->display_name;	

				$edit_link = get_edit_post_link($child->md_post_id);

				$last_updated = md_last_updated($child->md_post_id);

				$status = $child->md_status == 'md-draft' ? 'in progress' : ($child->md_status == 'md-pending' ? 'pending review' : 'status unknown');

				echo "<li><p><a href='" . $edit_link . "'><strong>" . $author_name . "</strong></a> (" . $status . ")<br />Last updated " . $last_updated . "</p></li>";

			}

			?>

			</ul>

		</div>

	<?php 

	} 

	?>

		<div class='clear'></div>

	</div>

	<?php

}

/*
 * MD_METADRAFT_COMMENTS_METABOX
 * Provides a framework for building comments metabox on the metadraft edit 
 * screen
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_metadraft_comments_metabox($post){

	$metadraft = md_get_metadraft($post->ID);
	$source = md_get_source($post->ID);

	echo "<div id='md_comments_inner'>";

	// Provide a hook to attach metabox content in discrete chunks
	do_action('md_metadraft_comments_metabox', $post, $metadraft, $source);

	echo "</div>";

}

add_action('md_metadraft_comments_metabox', 'md_comment_form', 1, 3);
function md_comment_form($post, $metadraft, $source){

	if(substr($metadraft->md_status, 0, 9) != 'md-closed'){

	?>

	<div id='md_comment_form'>
		<textarea name='md_general_comment_content' id='md_general_comment_field' placeholder='Add a comment to this draft.'></textarea>
		<input type='submit' name='md_submit_comment' id='md_submit_comment' value='Submit Comment' class='button'>
	</div>

	<?php

	}
}

add_action('md_metadraft_comments_metabox', 'md_comment_list', 2, 3);
function md_comment_list($post, $metadraft, $source){

	$comments = md_get_comments($metadraft->md_id);

	$comments_class = $comments ? '' : 'no_comments';
	$form_class = substr($metadraft->md_status, 0, 9) == 'md-closed' ? 'no_form' : '';

	$user_id = get_current_user_id();

	echo "<div id='md_comment_list'>";
	echo "<ul class='list " . $comments_class . ' ' . $form_class. "'>";

	foreach($comments as $comment){

		$author = get_userdata($comment->cmt_author_id);
		$author_name = $author->display_name;
		$author_avatar = get_avatar($comment->cmt_author_id, 96);

		$content = $comment->cmt_content;

		$date = $comment->cmt_date_posted;

		$type = $comment->cmt_type;

		$comment_id = $comment->cmt_id;

		if(!md_has_seen_comment($comment_id, $user_id)){
			md_add_comment_viewer($comment_id, $user_id);
		}

		?>

		<li class='md_comment_item'>

			<?php echo $author_avatar; ?>

			<div class='content'>

				<h4 class='byline'>
					<span class='author'><?php echo $author_name; ?></span>
					<?php 
					switch($type){
						case 'init':
							echo "<span class='action'>created the draft.</span>";
							break;
						case 'general':
							echo "<span class='action'>commented:</span>";
							break;
						case 'review-request':
							echo "<span class='action'>requested a draft review:";
							break;
						case 'closed-applied':
							if($metadraft->md_is_orig){
								echo "<span class='action'>published the draft.</span>";
							} else {
								echo "<span class='action'>applied the changes.</span>";
							}
							break;
						case 'closed-trashed':
							echo "<span class='action'>discarded the draft.</span>";
							break;
					}
					?>
				</h4>

				<p class='md_comment'>
					<?php echo nl2br(stripslashes($content)); ?>
				</p>

			</div>

		</li>
	<?php
	}

	echo "</div>";

}

