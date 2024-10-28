<?php
/*
 * Remove configuration variables
 */
function smart_excerpt_remove_configuration_variables() {
	delete_option ( 'minLen' );
	delete_option ( 'maxLen' );
	delete_option ( 'useTitle' );
	delete_option ( 'useTags' );
	delete_option ( 'featured' );
	delete_option ( 'more' );
	delete_option ( 'keepHtmlTags' );
	delete_option ( 'numSentences' );
	delete_option ( 'skipManual' );
} // End smart_excerpt_remove_configuration_variables
function smart_excerpt_deactivePlugin() {
	global $wpdb;
	if (function_exists ( 'is_multisite' ) && is_multisite ()) {
		$old_blog = $wpdb->blogid;
		// Get all blog ids
		$blogids = $wpdb->get_col ( "SELECT blog_id FROM $wpdb->blogs" );
		foreach ( $blogids as $blog_id ) {
			switch_to_blog ( $blog_id );
			smart_excerpt_remove_configuration_variables ();
		}
		switch_to_blog ( $old_blog );
		return;
	}
	smart_excerpt_remove_configuration_variables ();
} // End smart_excerpt_deactivePlugin
if (WP_UNINSTALL_PLUGIN) {
	smart_excerpt_deactivePlugin ();
}
?>