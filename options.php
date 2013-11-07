<?php

add_action('show_user_profile', 'md_manager_settings');
function md_manager_settings($user){
  if(current_user_can('manage_metadrafts')){
    $md_notify_on_review_request_array = get_option('md_notify_on_review_request');
    $md_notify_on_review_request_checked = is_array($md_notify_on_review_request_array) && in_array($user->id, $md_notify_on_review_request_array) ? 'checked' : '';
    $md_notify_on_comment_array = get_option('md_notify_on_comment');
    $md_notify_on_comment_checked = is_array($md_notify_on_comment_array) && in_array($user->id, $md_notify_on_comment_array) ? 'checked' : '';
    ?>
    <h3>Editor Settings</h3>
    <table class="form-table">
      <tr>
        <th>Notify on review request</th>
        <td>
          <input type="checkbox" name="md_notify_on_review_request" id="md_notify_on_review_request" value="1" <?php echo $md_notify_on_review_request_checked; ?> />
          <label for="md_notify_on_review_request">Send me an email when an author requests an editorial review of a post.</label>
        </td>
      </tr>
      <tr>
        <th>Notify on comment</th>
        <td>
          <input type="checkbox" name="md_notify_on_comment" id="md_notify_on_comment" value="1" <?php echo $md_notify_on_comment_checked; ?> />
          <label for="md_notify_on_comment">Send me an email when someone comments on a draft (does not include editoral review requests).</label>
        </td>
      </tr>
    </table>
  <?php 
  }
}

add_action( 'personal_options_update', 'md_handle_manager_settings_submission' );
function md_handle_manager_settings_submission($user_id){

  if(!current_user_can('edit_user', $user_id)){ 
    return false; 
  }

  $md_notify_on_review_request_array = get_option('md_notify_on_review_request');
  if($_POST['md_notify_on_review_request']){
    $md_notify_on_review_request_array[$user_id] = $user_id;
  } else {
    unset($md_notify_on_review_request_array[$user_id]);
  }
  update_option('md_notify_on_review_request', $md_notify_on_review_request_array);

  $md_notify_on_comment_array = get_option('md_notify_on_comment');
  if($_POST['md_notify_on_comment']){
    $md_notify_on_comment_array[$user_id] = $user_id;
  } else {
    unset($md_notify_on_comment_array[$user_id]);
  }
  update_option('md_notify_on_comment', $md_notify_on_comment_array);

}