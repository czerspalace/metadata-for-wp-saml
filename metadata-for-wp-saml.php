<?php
/**
 * Plugin Name: Metadata for WP SAML
 * Description: Metadata endpoint for WP Saml auth plugin
 * Version: 0.1
 */

add_action( 'admin_init', 'md_plugin_for_wp_saml_has_wp_saml' );
add_action( 'init', 'md_for_wpsaml_init' );
add_filter( 'feed_content_type', 'md_for_saml_content_type', 10, 2 );
add_filter( 'wp_headers', 'md_for_wpsaml_download', 10, 2 );

/**
 * Require WP SAML plugin before enabling metadata plugin.
 */
function md_plugin_for_wp_saml_has_wp_saml() {
    if ( is_admin() && current_user_can( 'activate_plugins' ) &&  ! is_plugin_active( 'wp-saml-auth/wp-saml-auth.php' ) ) {
        add_action( 'admin_notices', 'md_plugin_dependency_notice' );

        deactivate_plugins( plugin_basename( __FILE__ ) ); 

        if ( isset( $_GET['activate'] ) ) {
            unset( $_GET['activate'] );
        }
    }
}

/**
 * Admin notice if WP Saml Auth not enabled.
 */
function md_plugin_dependency_notice(){
    ?><div class="error"><p>Sorry, but Metadata for WP SAML requires <a href="https://wordpress.org/plugins/wp-saml-auth/" target="_blank">WP SAML Auth plugin</a> to be installed and active.</p></div><?php
}

/**
 * Create new feed to display metadata.
 */
function md_for_wpsaml_init() {
	add_feed( 'saml-metadata', 'md_for_wpsaml_markup' );
}

/**
 * Generate output for metadata feed.
 */
function md_for_wpsaml_markup() {
	
	$saml = WP_SAML_Auth::get_instance();
	$provider = $saml->get_provider();
	
	$settings = $provider->getSettings();
	$metadata = null;
	try {
		$metadata = $settings->getSPMetadata();
		$errors   = $settings->validateMetadata( $metadata );
	} catch ( \Exception $e ) {
		$errors = $e->getMessage();
	}

	if ( $errors ) {
		wp_die( 'Could not genereate metadata. Review your configuration.' );
	}

	echo $metadata;
	exit;
}

/**
 * Set content type for feed.
 */
function md_for_saml_content_type( $content_type, $type ) {
    if( 'saml-metadata' == $type ) {
        $content_type = 'text/xml';
    }
    return $content_type;
}

/**
 * Set content-disposition if requesting a download of feed.
 *
 * @param array $headers Headers to be sent for feed.
 * @param WP    $wp      Current WordPress environment.
 */
function md_for_wpsaml_download( $headers, $wp ){
	
	if ( empty( $wp->query_vars ) ) {
		return $headers;
	}
	
	if ( empty( $wp->query_vars['feed'] ) ) {
		return $headers;
	}
	
	$feed = $wp->query_vars['feed'];
	
	if ( $feed != 'saml-metadata' ) {
		return $headers;
	}
	
	if ( ! empty( $_GET['download'] ) ) {
		$headers['Content-Disposition'] = 'attachment; filename=samlmetadata.xml';
	}
	
	$headers['Last-Modified'] = false;
	$headers['ETag'] = current_time( 'timestamp' );
	return $headers;
	
}
