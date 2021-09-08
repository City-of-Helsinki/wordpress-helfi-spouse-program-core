<?php
/**
 * Description: Contact form 7 custom functionalities
 */

// Catch sign in form submission
add_action("wpcf7_before_send_mail", "spouse_create_user_on_signup_form_submission");

add_filter('wpcf7_validate_email*', 'custom_email_confirmation_validation_filter', 5, 2 );
add_filter('wpcf7_validate_text*', 'custom_username_confirmation_validation_filter', 5, 2 );

function custom_email_confirmation_validation_filter( $result, $tag ) {
  $wpcf7 = WPCF7_ContactForm::get_current();

  if ($wpcf7->id() != 22) {
    return $result;
  }

  if ('email' == $tag->name) {
    $form = WPCF7_Submission::get_instance();
    $values = $form->get_posted_data();
    $email = sanitize_email($values['email']);

    if (email_exists($email)) {
      $result->invalidate( $tag, "Email already exists" );
    }
  }

  return $result;
}

function custom_username_confirmation_validation_filter( $result, $tag ) {
  $wpcf7 = WPCF7_ContactForm::get_current();

  if ($wpcf7->id() != 22) {
    return $result;
  }

  if ('username' == $tag->name) {
    $form = WPCF7_Submission::get_instance();
    $values = $form->get_posted_data();
    $username = sanitize_text_field($values['username']);

    if (username_exists($username)) {
      $result->invalidate( $tag, "Username already exists" );
    }
  }

  return $result;
}

function spouse_create_user_on_signup_form_submission(&$contact_form) {
  if ($contact_form->id() != 22) {
    return;
  }

  $form = WPCF7_Submission::get_instance();
  $values = $form->get_posted_data();

  $email = sanitize_email($values['email']);
  $username = sanitize_text_field($values['username']);
  $password = spouse_random_str(20);

  $result = wp_create_user($username, $password, $email);

  if (is_wp_error($result)) {
    return __('Could not create user.', 'custom');
  }

  $reset_link = wp_login_url() . "\r\n";

  $blogName = get_bloginfo('name');
  $message = "Hi $username, \r\n";
  $message .= "An account has been created on $blogName for email address $email \r\n";
  $message .= "Username for your account: $username \r\n";
  $message .= "Automatically created password for your account: $password \r\n";
  $message .= "You can change your password on the profile \r\n";
  $message .= "You can login here: $reset_link \r\n";

  $subject = __("Your account on ".get_bloginfo( 'name'));
  $headers = [];
  $headers[] = "From: Spouse-program <noreply@spouseprogram.fi> \r\n";

  wp_mail($email, $subject, $message, $headers);
}

function spouse_create_event_on_form_submission(&$contact_form){
  global $current_user;

  if ($contact_form->id() != 593) {
    return;
  }

  $form = WPCF7_Submission::get_instance();
  $values = $form->get_posted_data();

  $title = sanitize_text_field($values['event-title']);
  $description = sanitize_textarea_field($values['description']);

  $eventdata = array(
    'post_title'   => $title,
    'post_content' => $description,
    'post_type'    => 'eventbrite_events',
    'post_status'  => 'pending',
    'post_author'  => get_current_user_id(),
  );

  wp_insert_post($eventdata);

}

// Catch sign in form submission
add_action("wpcf7_before_send_mail", "spouse_create_event_on_form_submission");

function spouse_random_str(
  $length,
  $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_.,'
)
{
  $str = '';
  $max = mb_strlen($keyspace, '8bit') - 1;
  if ($max < 1) {
    throw new Exception($keyspace.'must be at least two characters long');
  }
  for ($i = 0; $i < $length; ++$i) {
    $str .= $keyspace[random_int(0, $max)];
  }
  return $str;
}

function filter_wpcf7_validation_error( $error, $name, $instance ) {
    $submission = WPCF7_Submission::get_instance();
    $invalid_fields = $submission->get_invalid_fields();

    $posted_data = $submission->get_posted_data();
  return $error;
};

// add the filter
add_filter( 'wpcf7_validation_error', 'filter_wpcf7_validation_error', 10, 3 );

add_filter( 'wpcf7_display_message', 'spouse_validation_messages_fail', 10, 2 );

function spouse_validation_messages_fail( $message, $status ) {
  $submission = WPCF7_Submission::get_instance();

  if ( $submission->is( 'validation_failed' ) ) {
    $invalid_fields = $submission->get_invalid_fields();
    $fields = implode(', ',array_keys($invalid_fields));
    $message = 'Your form has invalid values in fields: '. $fields;

    return $message;
  }

  return $message;
}
