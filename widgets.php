<?php

add_action( 'wp_dashboard_setup', 'md_enqueue_dashboard_widgets' );
function md_enqueue_dashboard_widgets() {

	$user_id = get_current_user_id();

	if(current_user_can('manage_metadrafts')){

		wp_add_dashboard_widget(
			'md_manage_metadrafts_widget',
			'Manage Active Drafts',
			'md_manage_metadrafts_widget_content'
		);	

	}

	wp_add_dashboard_widget(
		'md_my_metadrafts_widget',
		'Active Drafts Written By Me',
		'md_my_metadrafts_widget_content'
	);

	wp_add_dashboard_widget(
		'md_my_closed_metadrafts_widget',
		'Closed Drafts Written By Me',
		'md_my_closed_metadrafts_widget_content'
	);

}

function md_manage_metadrafts_widget_content(){

	$metadrafts = md_get_active_metadrafts();
	if(empty($metadrafts)){
	?>
		<div class='metadrafts_widget' id='my_closed_metadrafts_widget'>
			<p class='md_no_results'>No active drafts.</p>
		</div>
	<?php
		return;
	}

?>

	<div class='metadrafts_widget'>

		<div class='metadrafts_list' id='manage_metadrafts_list'>
			<!-- Do not alter classname 'list' (required by list.js) -->
			<ul class='list'>

			<?php

			foreach($metadrafts as $metadraft){

				$post_type = get_post_type($metadraft->md_post_id);
				$post_type_object = get_post_type_object($post_type);
				$post_type_label = $post_type_object->labels->singular_name;

				$author = get_userdata($metadraft->md_author_id);
				$author_name = $author->display_name;
				$author_avatar = get_avatar($metadraft->md_author_id, 96);

				$edit_link = get_edit_post_link($metadraft->md_post_id);
				$permalink = get_permalink($metadraft->md_post_id);
				if(!$metadraft->md_is_orig){
					$source_permalink = get_permalink($metadraft->src_post_id);
				}

				$updated_sort = md_last_updated($metadraft->md_post_id, 'sortable');
				$updated_text = md_last_updated($metadraft->md_post_id, 'titlecase');

				$message = 'Your message here.';

				$status_slug = $metadraft->md_status;
				$status = $status_slug == 'md-pending' ? 'Ready to Go' : ($status_slug == 'md-draft' ? 'In Progress' : 'Status unknown');
				$status_sort = $status_slug == 'md-pending' ? '1' : ($status_slug == 'md-draft' ? '2' : '3'); 

				$title = get_the_title($metadraft->md_post_id);
				if(strlen($title) > 50){
					$title = substr($title, 0, 50) . '...';
				}

				?>

				<li class='metadrafts_list_item <?php echo $metadraft->md_post_id; ?>'>

					<span class='hidden status'><?php echo $status_sort; ?></span>
					<span class='hidden updated'><?php echo $updated_sort; ?></span>

					<div class='status_indicator <?php echo $status_slug; ?>'></div>

					<?php echo $author_avatar; ?>

					<div class='content'>

						<h4>
							<span class='byline'>
							<?php if ($metadraft->md_is_orig){ ?>
								<span class='author'><?php echo $author_name; ?></span> is drafting a <span class='type'>new <?php echo strtolower($post_type_label); ?></span>:
							<?php } else { ?>
								<span class='author'><?php echo $author_name; ?></span> is revising an <span class='type'>existing <?php echo strtolower($post_type_label); ?></span>:
							<?php } ?>
							</span>
							<span class='title'><a href='<?php echo $edit_link; ?>'><?php echo $title; ?></a></span>
						</h4>

						<p class='status_display'>
							<span class='status_box <?php echo $status_slug; ?>'></span>
							<span class='status_name'><?php echo $status; ?></span>
							<span class='updated_display'> &mdash; Updated <?php echo $updated_text; ?></span>
						</p>

						<p class='row_actions'>
							<a href='<?php echo $edit_link; ?>'>Edit</a> | 
						<?php if($metadraft->md_is_orig){ ?>
							<a href='<?php echo $permalink; ?>' target='_blank'>Preview Draft</a>
							<?php if($status_slug == 'md-pending'){ ?> | 
							<a href='#' class='md_ajax_apply' data-md-post-id='<?php echo $metadraft->md_post_id; ?>'>Publish</a>
							<?php } ?>
						<?php } else { ?>
							<a href='<?php echo $source_permalink; ?>' target='_blank'>Preview Original</a> | 
							<a href='<?php echo $permalink; ?>' target='_blank'>Preview Revision</a>
							<?php if($status_slug == 'md-pending'){ ?> | 
							<a href='#' class='md_ajax_apply' data-md-post-id='<?php echo $metadraft->md_post_id; ?>'>Apply Changes</a>
							<?php } ?>
						<?php } ?>
						</p>

					</div>
				</li>

				<?php 
				}
				?>

			</ul>
		</div>

		<p class='sort_menu'>
			<span class='label'>Sort by: </span>
			<span class='sort current' data-sort='status'>Status</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='author'>Author</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='title'>Title</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='updated'>Last Updated</span>
		</p>

	</div>

<?php
}

function md_my_metadrafts_widget_content(){

	$user_id = get_current_user_id();
	$metadrafts = md_get_user_metadrafts($user_id);
	
	if(empty($metadrafts)){
	?>
		<div class='metadrafts_widget' id='my_closed_metadrafts_widget'>
			<p class='md_no_results'>No active drafts.</p>
		</div>
	<?php
		return;
	}
?>

	<div class='metadrafts_widget'>

		<div class='metadrafts_list' id='my_metadrafts_list'>
			<!-- Do not alter classname 'list' (required by list.js) -->
			<ul class='list'>

			<?php

			foreach($metadrafts as $metadraft){

				$post_type = get_post_type($metadraft->md_post_id);
				$post_type_object = get_post_type_object($post_type);
				$post_type_label = $post_type_object->labels->singular_name;

				$edit_link = get_edit_post_link($metadraft->md_post_id);
				$permalink = get_permalink($metadraft->md_post_id);
				if(!$metadraft->md_is_orig){
					$source_permalink = get_permalink($metadraft->src_post_id);
				}

				$thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $metadraft->md_post_id ), "thumbnail");

				$updated_sort = md_last_updated($metadraft->md_post_id, 'sortable');
				$updated_text = md_last_updated($metadraft->md_post_id, 'titlecase');

				$message = 'Your message here.';

				$status_slug = $metadraft->md_status;
				$status = $status_slug == 'md-pending' ? 'Review Requested' : ($status_slug == 'md-draft' ? 'In Progress' : 'Status unknown');
				$status_sort = $status_slug == 'md-pending' ? '1' : ($status_slug == 'md-draft' ? '1' : '3'); 

				$title = get_the_title($metadraft->md_post_id);
				if(strlen($title) > 50){
					$title = substr($title, 0, 50) . '...';
				}

				?>

				<li class='metadrafts_list_item'>

					<span class='hidden status'><?php echo $status_sort; ?></span>
					<span class='hidden updated'><?php echo $updated_sort; ?></span>

					<div class='status_indicator <?php echo $status_slug; ?>'></div>

					<div class='thumbnail'>
						<?php if($thumbnail_src){ echo "<img src='" . $thumbnail_src[0] . "' />"; } ?>
					</div>

					<div class='content'>

						<h4>
							<span class='byline'>
							<?php if ($metadraft->md_is_orig){ ?>
								You are drafting a <span class='type'>new <?php echo strtolower($post_type_label); ?></span>:
							<?php } else { ?>
								You are revising an <span class='type'>existing <?php echo strtolower($post_type_label); ?></span>:
							<?php } ?>
							</span>
							<span class='title'><a href='<?php echo $edit_link; ?>'><?php echo $title; ?></a></span>
						</h4>

						<p class='status_display'>
							<span class='status_box <?php echo $status_slug; ?>'></span>
							<span class='status_name'><?php echo $status; ?></span>
							<span class='updated_display'> &mdash; Updated <?php echo $updated_text; ?></span>
						</p>

						<p class='row_actions'>
							<a href='<?php echo $edit_link; ?>'>Edit</a> | 
						<?php if($metadraft->md_is_orig){ ?>
							<a href='<?php echo $permalink; ?>' target='_blank'>Preview Draft</a>
						<?php } else { ?>
							<a href='<?php echo $source_permalink; ?>' target='_blank'>Preview Original</a> | 
							<a href='<?php echo $permalink; ?>' target='_blank'>Preview Revision</a>
						<?php } ?>
						</p>

					</div>
				</li>

			<?php 
			}
			?>

			</ul>
		</div>

		<p class='sort_menu'>
			<span class='label'>Sort by: </span>
			<span class='sort current' data-sort='updated'>Last Updated</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort ' data-sort='status'>Status</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='title'>Title</span>
		</p>

	</div>

<?php
}

function md_my_closed_metadrafts_widget_content(){

	$user_id = get_current_user_id();
	$metadrafts = md_get_closed_user_metadrafts($user_id);

	if(empty($metadrafts)){
	?>
		<div class='metadrafts_widget' id='my_closed_metadrafts_widget'>
			<p class='md_no_results'>No closed drafts.</p>
		</div>
	<?php
		return;
	}

?>

	<div class='metadrafts_widget' id='my_closed_metadrafts_widget'>

		<div class='metadrafts_list' id='my_closed_metadrafts_list'>
			<!-- Do not alter classname 'list' (required by list.js) -->
			<ul class='list'>

			<?php

			foreach($metadrafts as $metadraft){

				$thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $metadraft->md_post_id ), "thumbnail");

				$date_closed_sort = $metadraft->md_date_closed;
				// Incorrect
				// TODO: Split md_last_updated into md_dt_format! And generally rework this ... 
				$date_closed_display = $metadraft->md_date_closed;

				$message = 'Your message here.';

				$status_slug = $metadraft->md_status;
				$status_sort = $status_slug == 'md-closed-applied' ? '1' : ($status_slug == 'md-closed-trashed' ? '2' : '3'); 
				if($status_slug == 'md-closed-applied'){
					if($metadraft->md_is_orig){
						$status = 'Published';
						$action = ' published your draft';
					} else {
						$status = 'Changes Applied';
						$action = ' applied your changes to';
					}
				} else if ($status_slug == 'md-closed-trashed'){
					$status = 'Discarded';
					if($metadraft->md_is_orig){
						$action = ' discarded your draft';
					} else {
						$action = ' discarded your changes to';
					}
				} else {
					$status = 'Status Unknown';
					if($metadraft->md_is_orig){
						$action = ' closed your draft';
					} else {
						$action = ' closed your changes to';
					}
				}

				$title = get_the_title($metadraft->md_post_id);
				if(strlen($title) > 50){
					$title = substr($title, 0, 50) . '...';
				}

				$closer_id = $metadraft->md_closed_by;
				$closer = get_userdata($closer_id);
				$closer_name = $closer_id == $user_id ? 'You' : $closer->display_name;

				?>

				<li class='metadrafts_list_item'>

					<span class='hidden status'><?php echo $status_sort; ?></span>
					<span class='hidden date_closed'><?php echo $date_closed_sort; ?></span>

					<div class='status_indicator <?php echo $status_slug; ?>'></div>

					<div class='thumbnail'>
						<?php if($thumbnail_src){ echo "<img src='" . $thumbnail_src[0] . "' />"; } ?>
					</div>

					<div class='content'>

						<h4>
							<span class='byline <?php echo $status_slug; ?>'>
								<span class='closer'><?php echo $closer_name; ?></span><?php echo $action; ?>:
							</span>
							<span class='title <?php echo $status_slug; ?>'>
								<?php echo $title; ?>
							</span>
						</h4>

						<p class='status_display  <?php echo $status_slug; ?>'>
							<span class='status_box <?php echo $status_slug; ?>'></span>
							<span class='status_name'><?php echo $status; ?></span>
							<span class='date_closed_display'> &mdash; <?php echo $date_closed_display; ?></span>
						</p>

					</div>
				</li>

			<?php 
			}
			?>

			</ul>
		</div>

		<p class='sort_menu'>
			<span class='label'>Sort by: </span>
			<span class='sort current' data-sort='date_closed'>Date Closed</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='status'>Status</span>&nbsp;&nbsp;|&nbsp;&nbsp;
			<span class='sort' data-sort='title'>Title</span>
		</p>

	</div>

<?php
}
