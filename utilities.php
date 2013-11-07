<?php

/*
 * MD_GET_METADRAFT_FOR
 *
 * Find the post ID of the current metadraft for a given user and source post
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $src_post_id The post ID of the source post
 * @param int $user_id The user's ID
 *
 * @return int The metadraft post ID or NULL on failure
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_metadraft_for($src_post_id, $user_id){

	global $wpdb;

	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$md_post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT md_post_id 
			FROM $md_metadrafts 
			WHERE src_post_id = %d 
			AND md_author_id = %d 
			AND (md_status = 'md-draft' 
				OR md_status = 'md-pending'
				OR md_status = 'md-auto-draft')", 
			$src_post_id, 
			$user_id
		)
	);

	return $md_post_id ? $md_post_id : NULL;

}

/*
 * MD_IS_METADRAFT
 *
 * Check if a post is a metadraft. 
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $post_id The post ID
 *
 * @return bool True if post is a metadraft.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_is_metadraft($post_id) {

	global $wpdb;

	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$md_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT md_id 
			FROM $md_metadrafts 
			WHERE md_post_id = %d", 
			intval($post_id)
		)
	);

	return $md_id ? true : false;

}

/*
 * MD_BUILD_METADRAFT
 *
 * Create a metadraft on the Wordpress side, inserting data into the posts 
 * and postmeta tables and inheriting from the source post if applicable
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $src_post_id The post ID of the source post
 * @param int $md_author_id The user ID of the metadraft author/owner
 * @param bool $md_is_orig True if this is a new draft (i.e. the source post
 * is merely an auto-draft placeholder)
 *
 * @return int The metadraft post ID or false on failure
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_build_metadraft($md_author_id, $md_is_orig, $src_post_id = NULL){
	
	global $wpdb;

	if($md_is_orig){

		// If building an original post, we must be on post-new.php.
		// That means post_type is in the query.
		$post_type = $_GET['post_type'];

		// Insert mostly empty metadraft post
		$md_args = array(
			'post_author' => $md_author_id,
			'post_status' => 'pending',
			'post_name' => 'metadraft-u' . $md_author_id,
			'post_type' => $post_type,
		);

		$md_post_id = wp_insert_post($md_args);

		md_register_metadraft($md_post_id, $md_author_id, $md_is_orig);

		return $md_post_id ? $md_post_id : false;

	} else {

		$source = get_post($src_post_id);

		// Generate metadraft post with basic data from posts table
		$md_args = array(
			'post_author' => $md_author_id,
			'post_content' => $source->post_content,
			'post_title' => $source->post_title,
			'post_excerpt' => $source->post_excerpt,
			'post_status' => 'pending',
			'post_name' => $source->post_name . '-rev-u' . $md_author_id,
			'post_parent' => $source->post_parent,
			'post_type' => $source->post_type,
		);

		$md_post_id = wp_insert_post($md_args, false);

		if ($md_post_id){

			// Copy postmeta
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", 
					$src_post_id
				), 
				OBJECT
			);

			foreach($meta_rows as $meta){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", 
						$md_post_id, 
						$meta->meta_key, 
						$meta->meta_value
					)
				);
			}

			// Copy terms
			$term_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_taxonomy_id, term_order FROM $wpdb->term_relationships WHERE object_id = %d", 
					$src_post_id
				), 
				OBJECT
			);

			foreach($term_rows as $term){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, %d)", 
						$md_post_id, 
						$term->term_taxonomy_id, 
						$term->term_order
					)
				);
			}

			$posts2posts = $wpdb->prefix . 'p2p';

			// Copy Posts 2 Posts connections (TO source post)	
			$connection_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $posts2posts WHERE p2p_to = %d", 
					$src_post_id
				), 
				OBJECT
			);

			// Paste Posts 2 Posts connections (TO source post) into metadraft
			foreach($connection_rows as $connection){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $posts2posts (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", 
						$connection->p2p_from, 
						$md_post_id, 
						$connection->p2p_type
					)
				);
			}

			// Copy Posts 2 Posts connections (FROM source post)
			$connection_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $posts2posts WHERE p2p_from = %d", 
					$src_post_id
				), 
				OBJECT
			);

			// Paste Posts 2 Posts connections (FROM source post) into metadraft
			foreach($connection_rows as $connection){
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO $posts2posts (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", 
						$md_post_id,
						$connection->p2p_to, 
						$connection->p2p_type
					)
				);
			}

			md_register_metadraft($md_post_id, $md_author_id, $md_is_orig, $src_post_id);

			return $md_post_id;

		} else {

			return false;

		}	
	}
}

/*
 * MD_REGISTER_METADRAFT
 *
 * Register a new metadraft in the metadrafts table.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 * @param int $md_author_id The user ID of the author
 * @param bool $md_is_orig True if this is a new draft (i.e. the source post
 * is merely an auto-draft placeholder)
 * @param int $src_post_id The post ID of the source post, if applicable
 *
 * @return int The metadraft ID (key of metadraft table)
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_register_metadraft($md_post_id, $md_author_id, $md_is_orig, $src_post_id = NULL){

	$original = $md_is_orig ? 1 : 0;

	global $wpdb;

	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	if($original){

		$wpdb->insert(
			$md_metadrafts,
			array(
				'md_post_id' => $md_post_id,
				'md_author_id' => $md_author_id,
				'md_status' => 'md-auto-draft',
				'md_is_orig' => $original
			),
			array(
				'%d',
				'%d',
				'%s',
				'%d'
			)
		
		);

		$md_id = $wpdb->insert_id;

	} else {

		$wpdb->insert(
			$md_metadrafts,
			array(
				'md_post_id' => $md_post_id,
				'src_post_id' => $src_post_id,
				'md_author_id' => $md_author_id,
				'md_status' => 'md-auto-draft',
				'md_is_orig' => $original
			),
			array(
				'%d',
				'%d',
				'%d',
				'%s',
				'%d'
			)
		);

		$md_id = $wpdb->insert_id;

	}

	return $md_id;

}

/*
 * MD_GET_METADRAFT
 *
 * Get all data for a metadraft.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 *
 * @return obj All fields in that row of the metadrafts table or NULL on 
 * failure
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_metadraft($md_post_id){
	
	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$metadraft = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM $md_metadrafts WHERE md_post_id = %d", 
			$md_post_id
		),
		OBJECT
	);

	return $metadraft;

}

/*
 * MD_GET_SOURCE
 *
 * Get the post object for a metadraft's source post
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 *
 * @return obj The post object of the metadraft's source, false if the post
 * is original or on failure
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_source($md_post_id){
	
	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$src_post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT src_post_id FROM $md_metadrafts WHERE md_post_id = %d", 
			$md_post_id
		)
	);

	if ($src_post_id){

		$source = get_post($src_post_id);

		return $source;

	} else {

		return false;

	}
}

/*
 * MD_UPDATE_STATUS
 *
 * Update the metadraft status in the metadrafts table
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 * @param string $md_status The desired status
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_update_status($md_post_id, $md_status){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE $md_metadrafts SET md_status = %s WHERE md_post_id = %d",
			$md_status,
			$md_post_id
		)
	);
}

/*
 * MD_APPLY_CHANGES
 *
 * Update the source post with data from the metadraft (creating the source
 * post in the process if the metadraft is original)
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 * @param int $user_id The ID of the user publishing the metadraft
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_apply_changes($md_post_id, $user_id){

	global $wpdb;
	global $post;

	$post = get_post($md_post_id);
	$metadraft = md_get_metadraft($md_post_id);
	$src_post_id = $metadraft->src_post_id;

	if($metadraft->md_status == 'md-closed-applied' || $metadraft->md_status == 'md-closed-trashed'){
		return;
	}

	// Temporarily remove action to prevent save loop
	remove_action('save_post', 'md_save_apply_changes');

	if($metadraft->md_is_orig && !$metadraft->src_post_id){

		// Update content, title, excerpt of post
		$args = array(
			'post_author' => $metadraft->md_author_id,
			'post_content' => $post->post_content,
			'post_title' => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
			'post_status' => 'publish',
			'post_parent' => $post->post_parent,
			'menu_order' => $post->menu_order,
			'post_type' => $post->post_type
		);

		$src_post_id = wp_insert_post($args);
		md_update_src($md_post_id, $src_post_id);

	} else {

		// Update content, title, excerpt of post
		$args = array(
			'ID' => $metadraft->src_post_id,
			'post_content' => $post->post_content,
			'post_title' => $post->post_title,
			'post_excerpt' => $post->post_excerpt,
		);

		wp_update_post($args);
	}

	// Clear out source postmeta
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->postmeta WHERE post_id = %d",
			$src_post_id
		)
	);
	
	// Copy metadraft postmeta
	$meta_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", 
			$md_post_id
		), 
		OBJECT
	);

	// Paste postmeta into source
	foreach($meta_rows as $meta){
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", 
				$src_post_id, 
				$meta->meta_key, 
				$meta->meta_value
			)
		);
	}

	// Clear out source terms
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $wpdb->term_relationships WHERE object_id = %d",
			$src_post_id
		)
	);

	// Copy metadraft terms
	$term_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT term_taxonomy_id, term_order FROM $wpdb->term_relationships WHERE object_id = %d", 
			$md_post_id
		), 
		OBJECT
	);

	// Paste terms into source
	foreach($term_rows as $term){
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->term_relationships (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, %d)", 
				$src_post_id, 
				$term->term_taxonomy_id, 
				$term->term_order
			)
		);
	}

	$posts2posts = $wpdb->prefix . 'p2p';

	// Clear out Posts 2 Posts connections for source
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM $posts2posts WHERE (p2p_from = %d OR p2p_to = %d)",
			$src_post_id,
			$src_post_id
		)
	);

	// Copy Posts 2 Posts connections (TO metadraft)
	$connection_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $posts2posts WHERE p2p_to = %d", 
			$md_post_id
		), 
		OBJECT
	);

	// Paste Posts 2 Posts connections (TO metadraft) into source
	foreach($connection_rows as $connection){
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $posts2posts (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", 
				$connection->p2p_from, 
				$src_post_id, 
				$connection->p2p_type
			)
		);
	}

	// Copy Posts 2 Posts connections (FROM metadraft)
	$connection_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $posts2posts WHERE p2p_from = %d", 
			$md_post_id
		), 
		OBJECT
	);

	// Paste Posts 2 Posts connections (FROM metadraft) into source
	foreach($connection_rows as $connection){
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $posts2posts (p2p_from, p2p_to, p2p_type) VALUES (%d, %d, %s)", 
				$src_post_id,
				$connection->p2p_to, 
				$connection->p2p_type
			)
		);
	}
	
	md_close_metadraft('apply', $md_post_id, $user_id);
	
	// Reinstate save action
	add_action('save_post', 'md_save_apply_changes');

}

/*
 * MD_UPDATE_SRC
 *
 * Update the source post ID record in the metadrafts table.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 * @param int $src_post_id The post ID of the source post
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_update_src($md_post_id, $src_post_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE $md_metadrafts SET src_post_id = %d WHERE md_post_id = %d",
			$src_post_id,
			$md_post_id
		)
	);
}

/*
 * MD_GET_SIBLINGS
 *
 * Get sibling metadrafts.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft
 *
 * @return array Numbered array of metadraft objects or NULL if no siblings
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_siblings($md_post_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$siblings = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $md_metadrafts 
			WHERE md_post_id != %d 
			AND (md_status = 'md-draft' 
				OR md_status = 'md-pending')
			AND src_post_id IN 
				(SELECT * FROM 
					(SELECT src_post_id 
					FROM $md_metadrafts 
					WHERE md_post_id = %d 
					AND src_post_id IS NOT NULL) 
				Alias) 
			",
			$md_post_id,
			$md_post_id
		), OBJECT
	);

	return $siblings;
}

/*
 * MD_LAST_UPDATED
 *
 * Get a formatted string indicating time elapsed since a post was updated.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft (or any post ID)
 * @param string $format The string format. Options are 'lowercase', 
 * 'titlecase', and 'sortable'
 *
 * @return string Number of days since the post was updated.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_last_updated($md_post_id, $format = 'lowercase'){

	global $wpdb;

	$last_updated = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_modified FROM $wpdb->posts WHERE ID = %d",
			$md_post_id
		)
	);

	if($format == 'sortable'){

		return $last_updated;

	}

	$last_updated_stamp = strtotime($last_updated);
	$last_updated_day_stamp = mktime(0,0,0,date('n', $last_updated_stamp),date('j', $last_updated_stamp),date('Y', $last_updated_stamp));
	$last_updated_time = date('g:i a', $last_updated_stamp);

	$today_day_stamp = mktime(0,0,0,date('n'),date('j'),date('Y'));

	if($last_updated_day_stamp == $today_day_stamp){

		if($format == 'titlecase'){
			return "Today at " . $last_updated_time;
		} else {
			return "today at " . $last_updated_time;
		}

	} else if ($today_day_stamp - $last_updated_day_stamp <= 60*60*24){

		if($format == 'titlecase'){
			return "Yesterday at " . $last_updated_time;
		} else {
			return "yesterday at " . $last_updated_time;
		}

	} else if ($today_day_stamp - $last_updated_day_stamp < 60*60*24*6){

		return date('l (M. j)', $last_updated_day_stamp) . " at " . $last_updated_time;

	} else {

		return date('M. j', $last_updated_day_stamp) . " at " . $last_updated_time;

	}
}

/*
 * MD_GET_CHILDREN
 *
 * Get metadrafts of the current post.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $src_post_id The post ID of the current (source) post
 *
 * @return array Numbered array of metadraft objects or NULL if no children
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_children($src_post_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$children = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $md_metadrafts 
			WHERE src_post_id = %d
			AND (md_status = 'md-draft'
				OR md_status = 'md-pending')",
			$src_post_id
		), OBJECT
	);

	return $children;

}

/*
 * MD_GET_USER_METADRAFTS
 *
 * Get active metadrafts associated with a user.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_author_id The user ID of the author.
 *
 * @return array Numbered array of metadraft objects or NULL if no drafts
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_user_metadrafts($md_author_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$metadrafts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $md_metadrafts AS md
			INNER JOIN $wpdb->posts AS p
			ON md.md_post_id = p.ID
			WHERE md_author_id = %d
			AND (md_status = 'md-draft'
				OR md_status = 'md-pending')
			ORDER BY p.post_modified DESC",
			$md_author_id
		), OBJECT
	);

	return $metadrafts;

}

/*
 * MD_GET_CLOSED_USER_METADRAFTS
 *
 * Get closed metadrafts associated with a user.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_author_id The user ID of the author.
 *
 * @return array Numbered array of metadraft objects or NULL if no results
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_closed_user_metadrafts($md_author_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$metadrafts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $md_metadrafts 
			WHERE md_author_id = %d
			AND (md_status = 'md-closed-applied'
				OR md_status = 'md-closed-trashed')
			ORDER BY md_date_closed DESC",
			$md_author_id
		), OBJECT
	);

	return $metadrafts;

}

/*
 * MD_GET_ACTIVE_METADRAFTS
 *
 * Get all active metadrafts.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @return array Numbered array of metadraft objects or NULL if no metadrafts
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_active_metadrafts(){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';

	$metadrafts = $wpdb->get_results(
		"SELECT * FROM $md_metadrafts 
		WHERE (md_status = 'md-draft'
		   OR  md_status = 'md-pending')
		ORDER BY md_status DESC",
		 OBJECT
	);

	return $metadrafts;

}

/*
 * MD_CLOSE_METADRAFT
 *
 * Mark a metadraft as trashed.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param string $action 'trash' or 'apply'
 * @param int $md_post_id The post ID of the metadraft.
 * @param int $user_id The ID of the user deleting the metadraft.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_close_metadraft($action, $md_post_id, $user_id){

	global $wpdb;
	$md_metadrafts = $wpdb->prefix . 'md_metadrafts';
	$now = date('Y-m-d H:i:s');

	if($action == 'trash'){

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $md_metadrafts 
				 SET md_status = 'md-closed-trashed', md_date_closed = '%s', md_closed_by = %d
				 WHERE md_post_id = %d",
				 $now,
				 $user_id,
				 $md_post_id
			)
		);

	} else if ($action == 'apply'){

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $md_metadrafts 
				 SET md_status = 'md-closed-applied', md_date_closed = '%s', md_closed_by = %d 
				 WHERE md_post_id = %d",
				 $now,
				 $user_id,
				 $md_post_id
			)
		);
	}
}

/*
 * MD_GET_COMMENTS
 *
 * Get comments for a metadraft.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_id The metadrafts table ID of the metadraft.
 *
 * @return array Numbered array of comment objects or NULL if no result.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_get_comments($md_id){

	global $wpdb;
	$md_comments = $wpdb->prefix . 'md_comments';

	$comments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM $md_comments
			WHERE md_id = %d
			ORDER BY cmt_date_posted DESC, cmt_type ASC",
			$md_id
		), OBJECT
	);

	return $comments;

}

/*
 * MD_ADD_COMMENT
 *
 * Add a comment to a metadraft.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $md_post_id The post ID of the metadraft.
 * @param int $cmt_author_id The user ID of the comment author.
 * @param string $cmt_content The comment content.
 * @param string $cmt_type The comment type ('general','review-request')
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_add_comment($md_post_id, $cmt_author_id, $cmt_type, $cmt_content = NULL ){

	// Only 'init' and 'closed' comments are allowed to be empty.
	if($cmt_content == NULL && $cmt_type != 'init' && $cmt_type != 'closed-trashed' && $cmt_type != 'closed-applied'){
		return;
	}

	$metadraft = md_get_metadraft($md_post_id);
	$md_id = $metadraft->md_id;
	$now = date('Y-m-d H:i:s');

	global $wpdb;
	$md_comments = $wpdb->prefix . 'md_comments';

	$wpdb->insert(
		$md_comments,
		array(
			'cmt_date_posted' => $now,
			'cmt_author_id' => $cmt_author_id,
			'md_id' => $md_id,
			'cmt_content' => $cmt_content,
			'cmt_type' => $cmt_type
		),
		array(
			'%s',
			'%d',
			'%d',
			'%s',
			'%s',
			'%s'
		)	
	);

	$cmt_id = $wpdb->insert_id;

	md_add_comment_viewer($cmt_id, $cmt_author_id);

	if('general' == $cmt_type){
		md_notify('comment', $md_post_id, $cmt_author_id);
	}

}

/*
 * MD_ADD_COMMENT_VIEWER
 *
 * Add a user to the seen_by list for a comment.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $cmt_id The ID of the comment
 * @param int $viewer_id The user ID of the viewer
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_add_comment_viewer($cmt_id, $viewer_id){

	global $wpdb;
	$md_comments_rel = $wpdb->prefix . 'md_comments_rel';
	$now = date('Y-m-d H:i:s');

	$wpdb->insert(
		$md_comments_rel,
		array(
			'cmt_id'=>$cmt_id,
			'user_id'=>$viewer_id,
			'rel_date_seen'=>$now
		),
		array(
			'%d',
			'%d',
			'%s'
		)
	);
}

/*
 * MD_HAS_SEEN_COMMENT
 *
 * Check if a user has seen a particular comment.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param int $cmt_id The ID of the comment
 * @param int $viewer_id The user ID of the viewer
 *
 * @return bool True if user has viewed the comment
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_has_seen_comment($cmt_id, $viewer_id){

	global $wpdb;
	$md_comments_rel = $wpdb->prefix . 'md_comments_rel';
	$now = date('Y-m-d H:i:s');

	$viewed = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT rel_id
			FROM $md_comments_rel
			WHERE cmt_id = %d
			AND user_id = %d",
			$cmt_id,
			$viewer_id
		)
	);

	return $viewed ? true : false;
}

/*
 * MD_NOTIFY
 *
 * Send a notification if appropriate.
 *
 * @author Tom Borger <tborger@artsmia.org>
 * 
 * @since 1.0
 *
 * @param string $type The type of notification
 * @param int $md_post_id The Post ID of metadraft
 * @param int $initiator_id The ID of the user whose action initated the
 * notification.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
function md_notify($type, $md_post_id, $initiator_id){

	// Get initiator info
	$initiator = get_userdata($initiator_id);
	$initiator_name = $initiator->display_name;
	$initiator_email = $initiator->user_email;

	// Get post info
	$title = get_the_title($md_post_id);
	$edit_link = get_edit_post_link($md_post_id, '');
	$metadraft = md_get_metadraft($md_post_id);
	$preview_link = get_permalink($md_post_id);
	if(!$metadraft->md_is_orig){
		$source_preview_link = get_permalink($metadraft->src_post_id);
	}

	$recipient_ids = array();
	$subject = '';
	$message = '';

	switch($type){
		case 'review-request':
			$recipient_ids = get_option('md_notify_on_review_request');
			$subject = $initiator_name . " requested a review of " . $title;
			$message = "<h2>Editorial Review Request</h2>";
			$message .= "<p><strong><a href='mailto:" . $initiator_email . "'>" . $initiator_name . "</a></strong> requested a review of <strong>" . $title . ".<strong></p>";
			$message .= "<p><a href='" . $edit_link . "'>Review draft &raquo;</a> | <a href='" . $preview_link . "'>Preview draft &raquo;</a>";
			if(!$metadraft->md_is_orig){
				$message .= " | <a href='" . $source_preview_link . "'>Preview original &raquo;</a>";
			}
			$message .= "</p>";
			break;
		case 'comment':
			$recipient_ids = get_option('md_notify_on_comment');
			$subject = 'New editorial comment';
			$message = 'Someone left a comment.';
			break;
	}

	foreach($recipient_ids as $recipient_id){
		$recipient = get_userdata($recipient_id);
		$recipient_email = $recipient->user_email;
		$success = wp_mail($recipient_email, $subject, $message, "Content-type: text/html");
		update_option('md_mail_status', $success);
	}

}

