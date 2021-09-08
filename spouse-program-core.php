<?php
/**
 * Plugin Name: Spouse Program Core
 * Description: Druid mu-plugins bundled together by ArtCloud
 */

function spouse_program_core_init() {
	$files = array(
		'acf-custom',
		'cf7-custom',
		'event-previewbox',
		'loginredirect-custom',
		'spouse-emailfrom',
		'spouse-notice',
		'spouse-slack-feed'
	);

	$plugin_path = untrailingslashit( plugin_dir_path( __FILE__ ) );

	foreach ($files as $file) {
		$file_path = $plugin_path . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . $file . '.php';
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}
add_action('plugins_loaded', 'spouse_program_core_init');
