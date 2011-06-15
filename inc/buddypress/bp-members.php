<?php

/**
 * Create a shorturl for a BP member profile
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param int $user_id The ID of the user whose profile should be the URL for the shorturl
 * @param str $type 'pretty' if you want the shorturl slug to be created from the user's nicename,
 *   or from the $keyword param. Otherwise 'normal' will create a randomly generated URL, as per
 *   the service's API. Note that $type and $keyword only do anything with YOURLS (not bit.ly, etc)
 * @param str $keyword The desired 'keyword' or slug of the shorturl. Note that this param is
 *   ignored if $type is not set to 'pretty'. Defaults to the user's user_login if $type == 'pretty'
 */
function wp_ozh_yourls_create_bp_member_url( $user_id, $type = 'normal', $keyword = false ) {
	
	// Check plugin is configured
	$service = wp_ozh_yourls_service();
	if( !$service )
		return 'Plugin not configured: cannot find which URL shortening service to use';
	
	// Mark this post as "I'm currently fetching the page to get its title"
	if( $user_id && !get_user_meta( $user_id, 'yourls_shorturl', true ) ) {
		update_user_meta( $user_id, 'yourls_fetching', 1 );
		update_user_meta( $user_id, 'yourls_shorturl', '' ); // temporary empty title to avoid loop on creating short URL
	}
	
	$url 	  = bp_core_get_user_domain( $user_id );
	$userdata = get_userdata( $user_id );
	$title    = bp_core_get_user_displayname( $user_id );
	
	// Only send a keyword if this is a pretty URL
	if ( 'pretty' == $type ) {
		if ( !$keyword )
			$keyword = $userdata->user_login;
	} else {
		$keyword = false;
	}
	
	// Get short URL
	$shorturl = wp_ozh_yourls_api_call( $service, $url, $keyword, $title );
	
	// Remove fetching flag
	if( $user_id )
		delete_user_meta( $user_id, 'yourls_fetching' );

	// Store short URL in a custom field
	if ( $user_id && $shorturl ) {
		update_user_meta( $user_id, 'yourls_shorturl', $shorturl );
	
		if ( $keyword )
			update_user_meta( $user_id, 'yourls_shorturl_name', $keyword );
	}

	return $shorturl;
}

/**
 * Outputs the displayed user's shorturl in the header
 *
 * Don't like the way this looks? Put the following in your theme's functions.php:
 *
 *   remove_action( 'bp_before_member_header_meta', 'wp_ozh_yourls_display_user_url' );
 *
 * and then use the template tags wp_ozh_yourls_get_displayed_user_url() and
 * wp_ozh_yourls_edit_link() to create your own markup in your theme.
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_display_user_url() {
	$shorturl = wp_ozh_yourls_get_displayed_user_url();
	
	if ( $shorturl ) {
	?>
		<span class="highlight shorturl">
			<?php printf( __( 'Short URL: <code>%s</code>', 'wp-ozh-yourls' ), $shorturl ) ?> <?php if ( wp_ozh_user_can_edit_url() ) : ?>&nbsp;<?php wp_ozh_yourls_user_edit_link() ?><?php endif ?>
		</span>
	<?php
	}
}
add_action( 'bp_before_member_header_meta', 'wp_ozh_yourls_display_user_url' );

/**
 * Echo the content of wp_ozh_yourls_get_displayed_user_url()
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_displayed_user_url() {
	echo wp_ozh_yourls_get_displayed_user_url();
}
	/**
	 * Return the displayed user's shorturl
	 *
	 * @package YOURLS WordPress to Twitter
	 * @since 1.5
	 *
	 * @return str $url The shorturt
	 */
	function wp_ozh_yourls_get_displayed_user_url() {
		global $bp;
		
		$url = isset( $bp->displayed_user->shorturl ) ? $bp->displayed_user->shorturl : '';
		
		return $url;
	}

/**
 * Echo the content of wp_ozh_yourls_get_user_edit_link()
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param int $user_id The id of the user. Defaults to the displayed user, then to the loggedin user
 * @param str $return 'html' to return a full link, otherwise just retrieve the URL
 */
function wp_ozh_yourls_user_edit_link( $user_id = false, $return = 'html' ) {
	echo wp_ozh_yourls_get_user_edit_link( $user_id, $return );
}
	/**
	 * Return the URL to a user's General Settings screen, where he can edit his shorturl
	 *
	 * @package YOURLS WordPress to Twitter
	 * @since 1.5
	 *
	 * @param int $user_id The id of the user. Defaults to the displayed user, then to the
	 *     loggedin user
	 * @param str $return 'html' to return a full link, otherwise just retrieve the URL
	 * @return str $link The link
	 */
	 function wp_ozh_yourls_get_user_edit_link( $user_id = false, $return = 'html' ) {
	 	global $bp;
	 	
	 	// If no user_id is passed, first try to default to the displayed user
	 	if ( !$user_id ) {
	 		$user_id = !empty( $bp->displayed_user->id ) ? $bp->displayed_user->id : false;
	 		$domain = !empty( $bp->displayed_user->domain ) ? $bp->displayed_user->domain : false;
	 	}
	 	
	 	// If there's still no user_id, get the logged in user
	 	if ( !$user_id ) {
	 		$user_id = !empty( $bp->loggedin_user->id ) ? $bp->loggedin_user->id : false;
	 		$domain = !empty( $bp->loggedin_user->domain ) ? $bp->loggedin_user->domain : false;
	 	}
	 	
	 	// If there's *still* no displayed user, bail
	 	if ( !$user_id ) {
	 		return false;
	 	}
	 	
	 	// If a $user_id was passed manually to the function, we'll need to set $domain
	 	if ( !isset( $domain ) ) {
	 		$domain = bp_core_get_user_domain( $user_id );
	 	}
	 	
	 	// Create the URL to the settings page
	 	$link = $domain . BP_SETTINGS_SLUG;
	 	
	 	// Add the markup if necessary
	 	if ( 'html' == $return ) {
	 		$link = sprintf( '<a href="%1$s">%2$s</a>', $link, __( 'Edit', 'wp-ozh-yourls' ) );	
	 	}
	 	
	 	return $link;
	 }
	
/**
 * USER SHORTURL EDITING
 */

/**
 * Renders the Edit field on the General Settings page
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_render_user_edit_field() {
	if ( !wp_ozh_user_can_edit_url() )
		return;
	
	$shorturl_name = get_user_meta( bp_displayed_user_id(), 'yourls_shorturl_name', true );

	?>
	
	<label for="shorturl"><?php _e( 'Short URL: ', 'wp-ozh-yourls' ) ?></label>
	<code><?php wp_ozh_yourls_shortener_base_url() ?></code><input type="text" name="shorturl" id="shorturl" value="<?php echo $shorturl_name ?>" class="settings-input" />
	<p class="description"><?php _e( 'Please note that YOURLS only supports a limited character set for short URLs. See <a href="http://yourls.org/#FAQ">the YOURLS FAQ</a> for more information on 32 vs 64 bit encoding.', 'wp-ozh-yourls' ) ?></p>
	
	<?php
}
add_action( 'bp_core_general_settings_before_submit', 'wp_ozh_yourls_render_user_edit_field' );

/**
 * Processes shorturl edits by the member and displays proper success/error messages
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_save_user_edit() {
	global $bp;
	
	if ( isset( $_POST['shorturl'] ) ) {
		$shorturl_name = untrailingslashit( trim( $_POST['shorturl'] ) );
		
		// Remove the limitation on duplicate shorturls
		// This is a temporary workaround
		define( 'YOURLS_UNIQUE_URLS', false );
		add_filter( 'yourls_remote_params', 'wp_ozh_yourls_remote_allow_dupes' );
		
		// First, try to create a URL with this name
		$shorturl = wp_ozh_yourls_create_bp_member_url( bp_displayed_user_id(), 'pretty', $shorturl_name );
		
		remove_filter( 'yourls_remote_params', 'wp_ozh_yourls_remote_allow_dupes' );
		
		if ( !$shorturl ) {
			// Something has gone wrong. Check to see whether this is a reversion to a
			// previous shorturl
			$expand = wp_ozh_yourls_api_call_expand( 'yourls-remote', $shorturl_name );
			
			if ( empty( $expand->longurl ) || $expand->longurl != $bp->displayed_user->domain ) {
				// No match.
				bp_core_add_message( __( 'That URL is unavailable. Please choose another.', 'wp-ozh-yourls' ), 'error' );
			} else {
				$shorturl = $expand->shorturl;
			}
		}
		
		if ( $shorturl ) {
			update_user_meta( bp_displayed_user_id(), 'yourls_shorturl', $shorturl );
			update_user_meta( bp_displayed_user_id(), 'yourls_shorturl_name', $shorturl_name );
			
			// Just in case this needs to be refreshed
			$bp->displayed_user->shorturl = $shorturl;
		}
	}
}
add_action( 'bp_core_general_settings_after_save', 'wp_ozh_yourls_save_user_edit' );

/**
 * Removes the 'source' parameter from remote YOURLS requests
 *
 * There is an exception hardcoded into YOURLS that will never allow multiple shorturls to be
 * created for the same longurl, even if the YOURLS installation has set YOURLS_UNIQUE_URLS to
 * false, if the API request comes from the source 'plugin'. That means that BP users will not
 * be able to edit their auto-created shorturls. This filter removes the 'source' parameter as 
 * a workaround.
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param array $params The API params
 * @return array $params The API params, less 'source'
 */
function wp_ozh_yourls_remote_allow_dupes( $params ) {	
	$params['source'] = '';
	
	return $params;
}

?>