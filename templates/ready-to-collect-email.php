<?php
/**
 * YayMail render template for "Ready to Collect" email.
 * This file is required by YayMail to render the built template content.
 */

defined( 'ABSPATH' ) || exit;

$template = RTC_YayMail_Email::get_instance()->template;

if ( ! empty( $template ) ) {
    $content = $template->get_content( $args );
    yaymail_kses_post_e( $content );
}
