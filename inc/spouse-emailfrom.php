<?php
/**
 * Description: Add no-reply@spouseprogram.fi as default email sender
 */

/* enter the email-address you want site to send from  */
function spouse_filter_wp_mail_from($email){
    return 'no-reply@spouseprogram.fi';
}

/* enter the full name you want displayed alongside the email address */
function spouse_filter_wp_mail_from_name($from_name){
    return "Spouse-program";
}
add_filter("wp_mail_from_name", "spouse_filter_wp_mail_from_name");
add_filter("wp_mail_from", "spouse_filter_wp_mail_from");
