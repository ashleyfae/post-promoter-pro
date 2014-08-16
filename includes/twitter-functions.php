<?php
/**
 * Return if twitter account is found
 * @return bool If the Twitter object exists
 */
function ppp_twitter_enabled() {
	global $ppp_social_settings;

	if ( isset( $ppp_social_settings['twitter'] ) && !empty( $ppp_social_settings['twitter'] ) ) {
		return true;
	}

	return false;
}

function ppp_tw_connect_display() {
	?>
	<div>
		<?php
		global $ppp_twitter_oauth, $ppp_social_settings;
		$tw_auth = $ppp_twitter_oauth->ppp_verify_twitter_credentials();

		if ( isset( $tw_auth['error'] ) ) {
			?><div class="update error"><p><?php echo $tw_auth['error']; ?></p></div><?php
		} ?>
		<?php $ppp_social_settings = get_option( 'ppp_social_settings' ); ?>

		<?php if ( !ppp_twitter_enabled() ) { ?>
			<?php $tw_authurl = $ppp_twitter_oauth->ppp_get_twitter_auth_url(); ?>
			<a href="<?php echo $tw_authurl; ?>"><img src="<?php echo PPP_URL; ?>/includes/images/sign-in-with-twitter-gray.png" /></a>
		<?php } else { ?>
		<div class="ppp-social-profile ppp-twitter-profile">
			<img class="ppp-social-icon" src="<?php echo $ppp_social_settings['twitter']['user']->profile_image_url_https; ?>" />
			<div class="ppp-twitter-info"><?php _e( 'Signed in as', 'ppp-txt' ); ?>:<br /><?php echo $ppp_social_settings['twitter']['user']->name; ?></div>
		</div>
		<a class="button-primary" href="<?php echo admin_url( 'admin.php?page=ppp-social-settings&ppp_social_disconnect=true&ppp_network=twitter' ); ?>" ><?php _e( 'Disconnect from Twitter', 'ppp-txt' ); ?></a>&nbsp;
		<a class="button-secondary" href="https://twitter.com/settings/applications" target="blank"><?php _e( 'Revoke Access via Twitter', 'ppp-txt' ); ?></a>
	</div>
	<?php }
}
add_action( 'ppp_connect_display-tw', 'ppp_tw_connect_display' );

function ppp_capture_twitter_oauth() {
	if ( isset( $_REQUEST['oauth_verifier'] ) && isset( $_REQUEST['oauth_token'] ) ) {
		global $ppp_twitter_oauth;
		$ppp_twitter_oauth->ppp_initialize_twitter();
		wp_redirect( admin_url( 'admin.php?page=ppp-social-settings' ) );
		die();
	}
}
add_action( 'admin_init', 'ppp_capture_twitter_oauth', 10 );

function ppp_disconnect_twitter() {
	global $ppp_social_settings;
	$ppp_social_settings = get_option( 'ppp_social_settings' );
	if ( isset( $ppp_social_settings['twitter'] ) ) {
		unset( $ppp_social_settings['twitter'] );
		update_option( 'ppp_social_settings', $ppp_social_settings );
	}
}
add_action( 'ppp_disconnect-twitter', 'ppp_disconnect_twitter', 10 );

/**
 * Given a message, sends a tweet
 * @param  string $message The Text to share as the body of the tweet
 * @return object          The Results from the Twitter API
 */
function ppp_send_tweet( $message, $post_id, $use_media = false ) {
	global $ppp_twitter_oauth;

	return apply_filters( 'ppp_twitter_tweet', $ppp_twitter_oauth->ppp_tweet( ppp_entities_and_slashes( $message ), $use_media ) );
}

/**
 * Combines the results from ppp_generate_share_content and ppp_generate_link into a single string
 * @param  int $post_id The Post ID
 * @param  string $name    The 'name' element from the Cron
 * @return string          The Full text for the social share
 */
function ppp_tw_build_share_message( $post_id, $name, $scheduled = true ) {
	$share_content = ppp_tw_generate_share_content( $post_id, $name );
	$share_link    = ppp_generate_link( $post_id, $name, $scheduled );

	return apply_filters( 'ppp_tw_build_share_message', $share_content . ' ' . $share_link );
}

/**
 * Generate the content for the shares
 * @param  int $post_id The Post ID
 * @param  string $name    The 'Name' from the cron
 * @return string          The Content to include in the social media post
 */
function ppp_tw_generate_share_content( $post_id, $name, $is_scheduled = true ) {
	global $ppp_options;
	$default_text = isset( $ppp_options['default_text'] ) ? $ppp_options['default_text'] : '';
	$ppp_post_override = get_post_meta( $post_id, '_ppp_post_override', true );

	if ( $is_scheduled && !empty( $ppp_post_override ) ) {
		$ppp_post_override_data = get_post_meta( $post_id, '_ppp_post_override_data', true );
		$name_array = explode( '_', $name );
		$day = 'day' . $name_array[1];
		$share_content = $ppp_post_override_data[$day]['text'];
	}

	// If an override was found, use it, otherwise try the default text content
	$share_content = ( isset( $share_content ) && !empty( $share_content ) ) ? $share_content : $default_text;

	// If the content is still empty, just use the post title
	$share_content = ( isset( $share_content ) && !empty( $share_content ) ) ? $share_content : get_the_title( $post_id );

	return apply_filters( 'ppp_share_content', $share_content, array( 'post_id' => $post_id ) );
}

function ppp_tw_use_media( $post_id, $day ) {
	if ( empty( $post_id ) || empty( $day ) ) {
		return false;
	}

	$override_data = get_post_meta( $post_id, '_ppp_post_override_data', true );
	$use_media = isset( $override_data['day' . $day]['use_image'] ) ? true : false;

	return $use_media;
}

function ppp_set_tw_token_constants( $social_tokens ) {
	if ( !empty( $social_tokens ) && property_exists( $social_tokens, 'twitter' ) ) {
		define( 'PPP_TW_CONSUMER_KEY', $social_tokens->twitter->consumer_token );
		define( 'PPP_TW_CONSUMER_SECRET', $social_tokens->twitter->consumer_secret );
	}
}
add_action( 'ppp_set_social_token_constants', 'ppp_set_tw_token_constants', 10, 1 );

function ppp_tw_add_admin_tab( $tabs ) {
	$tabs['tw'] = array( 'name' => __( 'Twitter', 'ppp-txt' ), 'class' => 'icon-ppp-tw' );

	return $tabs;
}
add_filter( 'ppp_admin_tabs', 'ppp_tw_add_admin_tab', 10, 1 );

function ppp_tw_register_admin_social_content( $content ) {
	$content[] = 'tw';

	return $content;
}
add_filter( 'ppp_admin_social_content', 'ppp_tw_register_admin_social_content', 10, 1 );

function ppp_tw_add_meta_tab( $tabs ) {
	global $ppp_social_settings;
	if ( !isset( $ppp_social_settings['twitter'] ) ) {
		return $tabs;
	}

	$tabs['tw'] = array( 'name' => __( 'Twitter', 'ppp-txt' ), 'class' => 'icon-ppp-tw' );

	return $tabs;
}
add_filter( 'ppp_metabox_tabs', 'ppp_tw_add_meta_tab', 10, 1 );

function ppp_tw_register_metabox_content( $content ) {
	global $ppp_social_settings;
	if ( !isset( $ppp_social_settings['twitter'] ) ) {
		return $content;
	}

	$content[] = 'tw';

	return $content;
}
add_filter( 'ppp_metabox_content', 'ppp_tw_register_metabox_content', 10, 1 );

function ppp_tw_add_metabox_content( $post ) {
	global $ppp_options;
	$default_text = !empty( $ppp_options['default_text'] ) ? $ppp_options['default_text'] : __( 'Social Text', 'ppp-txt' );

	$ppp_post_exclude = get_post_meta( $post->ID, '_ppp_post_exclude', true );

	$ppp_share_on_publish = get_post_meta( $post->ID, '_ppp_share_on_publish', true );
	$ppp_share_on_publish_text = get_post_meta( $post->ID, '_ppp_share_on_publish_text', true );
	$ppp_share_on_publish_include_image = get_post_meta( $post->ID, '_ppp_share_on_publish_include_image', true );

	$ppp_post_override = get_post_meta( $post->ID, '_ppp_post_override', true );
	$ppp_post_override_data = get_post_meta( $post->ID, '_ppp_post_override_data', true );

	$exclude_style = ( !empty( $ppp_post_exclude ) ) ? 'display: none;' : '';
	$override_style = ( empty( $ppp_post_override ) ) ? 'display: none;' : '';
	?>
			<p>
			<?php $disabled = ( $post->post_status === 'publish' && time() > strtotime( $post->post_date ) ) ? true : false; ?>
			<input <?php if ( $disabled ): ?>readonly<?php endif; ?> type="checkbox" name="_ppp_share_on_publish" id="ppp_share_on_publish" value="1" <?php checked( '1', $ppp_share_on_publish, true ); ?> />&nbsp;
				<label for="ppp_share_on_publish"><?php _e( 'Tweet this post at the time of publishing?', 'ppp-txt' ); ?></label>
				<p class="ppp_share_on_publish_text" style="display: <?php echo ( $ppp_share_on_publish ) ? '' : 'none'; ?>">
						<input
						<?php if ( $disabled ): ?>readonly<?php endif; ?>
						onkeyup="PPPCountChar(this)"
						class="ppp-share-text"
						type="text"
						placeholder="<?php echo $default_text; ?>"
						name="_ppp_share_on_publish_text"
						<?php if ( isset( $ppp_share_on_publish_text ) ) {?>value="<?php echo htmlspecialchars( $ppp_share_on_publish_text ); ?>"<?php ;}?>
					/><span class="ppp-text-length"></span>
					<br />
					<input <?php if ( $disabled ): ?>readonly<?php endif; ?> id="ppp-share-on-publish-image" type="checkbox" name="_ppp_share_on_publish_include_image" value="1" <?php checked( '1', $ppp_share_on_publish_include_image, true ); ?>/>
						&nbsp;<label for="ppp-share-on-publish-image"><?php _e( 'Attach Featured Image In Tweet', 'ppp-txt' ); ?></label>
				</p>
			</p>
			<input type="checkbox" name="_ppp_post_exclude" id="_ppp_post_exclude" value="1" <?php checked( '1', $ppp_post_exclude, true ); ?> />&nbsp;
				<label for="_ppp_post_exclude"><?php _e( 'Do not schedule tweets for this post.', 'ppp-txt' ); ?></label>
			<br />
			<div style="<?php echo $exclude_style; ?>" class="ppp-post-override-wrap">
				<input type="checkbox" name="_ppp_post_override" id="_ppp_post_override" value="1" <?php checked( '1', $ppp_post_override, true ); ?> />&nbsp;
				<label for="_ppp_post_override"><?php _e( 'Override Default Text and Times', 'ppp-txt' ); ?></label>
				<div class="post-override-matrix" style="<?php echo $override_style; ?>">
					<?php
					$day = 1;
					while( $day <= ppp_share_days_count() ) {
						$enabled = isset( $ppp_post_override_data['day' . $day]['enabled'] ) ? '1' : false;
						$readonly = time() > strtotime( $post->post_date . ' +' . $day . ' day' ) ? true : false;
						if ( $post->post_status !== 'publish' && $post->post_status != 'future' ) {
							$readonly = false;
						}
						?>
						<p>
						<label for="day<?php echo $day; ?>"><?php printf( __( 'Day %s', 'ppp-txt' ), $day ); ?></label>&nbsp;
						<input <?php if ( $readonly ): ?>readonly<?php endif; ?> type="checkbox" class="ppp-share-enable-day" value="1" name="_ppp_post_override_data[day<?php echo $day; ?>][enabled]" <?php checked( '1', $enabled, true ); ?>/>&nbsp;
						<input <?php if ( !$enabled ): ?>readonly<?php endif; ?>
							 <?php if ( !$enabled || $readonly ): ?>readonly<?php endif; ?>
							id="day<?php echo $day; ?>"
							type="text"
							placeholder="<?php _e( 'Time', 'ppp-txt' ); ?>"
							name="_ppp_post_override_data[day<?php echo $day; ?>][time]"
							class="share-time-selector"
							value="<?php echo ( isset( $ppp_post_override_data['day' . $day]['time'] ) ) ? $ppp_post_override_data['day' . $day]['time'] : ppp_get_day_default_time( $day ); ?>"
							size="8"
						/>
						<input <?php if ( !$enabled ): ?>readonly<?php endif; ?>
							 <?php if ( !$enabled || $readonly ): ?>readonly<?php endif; ?>
							onkeyup="PPPCountChar(this)"
							class="ppp-share-text"
							type="text"
							placeholder="<?php echo $default_text; ?>"
							id="day<?php echo $day; ?>"
							name="_ppp_post_override_data[day<?php echo $day; ?>][text]"
							<?php if ( isset( $ppp_post_override_data['day' . $day]['text'] ) ) {?>value="<?php echo htmlspecialchars( $ppp_post_override_data['day' . $day]['text'] ); ?>"<?php ;}?>
						/>
						<span class="ppp-text-length"></span>
						<br />
						<?php $use_image = isset( $ppp_post_override_data['day' . $day]['use_image'] ) ? '1' : false; ?>
						<span class="ppp-override-meta">
							<input <?php if ( $readonly ): ?>readonly<?php endif; ?> type="checkbox" name="_ppp_post_override_data[day<?php echo $day; ?>][use_image]" value="1" <?php checked( '1', $use_image, true ); ?> />&nbsp;<?php _e( 'Attach Featured Image In Tweet', 'ppp-txt' ); ?>
						</span>
						</p>
						<?php
						$day++;
					}
					?>
				</div>
				<p><?php _e( 'Do not include links in your text, this will be added automatically.', 'ppp-txt' ); ?></p>
			</div>
	<?php
}
add_action( 'ppp_generate_metabox_content-tw', 'ppp_tw_add_metabox_content', 10, 1 );

/**
 * Save the items in our meta boxes
 * @param  int $post_id The Post ID being saved
 * @param  object $post    The Post Object being saved
 * @return int          The Post ID
 */
function ppp_tw_save_post_meta_boxes( $post_id, $post ) {
	global $ppp_options, $post;

	if ( empty ( $post ) ) { // if $post is empty, like on a brand spanking new post, just return, we're not saving
		return;
	}

	if ( !isset( $ppp_options['post_types'] ) || !is_array( $ppp_options['post_types'] ) || !array_key_exists( $post->post_type, $ppp_options['post_types'] ) ) {
		return;
	}

	$ppp_post_exclude = ( isset( $_REQUEST['_ppp_post_exclude'] ) ) ? $_REQUEST['_ppp_post_exclude'] : '0';

	$ppp_share_on_publish = ( isset( $_REQUEST['_ppp_share_on_publish'] ) ) ? $_REQUEST['_ppp_share_on_publish'] : '0';
	$ppp_share_on_publish_text = ( isset( $_REQUEST['_ppp_share_on_publish_text'] ) ) ? $_REQUEST['_ppp_share_on_publish_text'] : '';
	$ppp_share_on_publish_include_image = ( isset( $_REQUEST['_ppp_share_on_publish_include_image'] ) ) ? $_REQUEST['_ppp_share_on_publish_include_image'] : '';

	$ppp_post_override = ( isset( $_REQUEST['_ppp_post_override'] ) ) ? $_REQUEST['_ppp_post_override'] : '0';
	$ppp_post_override_data = isset( $_REQUEST['_ppp_post_override_data'] ) ? $_REQUEST['_ppp_post_override_data'] : array();

	update_post_meta( $post->ID, '_ppp_post_exclude', $ppp_post_exclude );

	update_post_meta( $post->ID, '_ppp_share_on_publish', $ppp_share_on_publish );
	update_post_meta( $post->ID, '_ppp_share_on_publish_text', $ppp_share_on_publish_text );
	update_post_meta( $post->ID, '_ppp_share_on_publish_include_image', $ppp_share_on_publish_include_image );

	update_post_meta( $post->ID, '_ppp_post_override', $ppp_post_override );

	// Fixes a bug when all items are unchecked from being checked, removed if statement
	update_post_meta( $post->ID, '_ppp_post_override_data', $ppp_post_override_data );

	return $post->ID;
}
add_action( 'save_post', 'ppp_tw_save_post_meta_boxes', 10, 2 ); // save the custom fields


function ppp_tw_share_on_publish( $old_status, $new_status, $post ) {
	global $ppp_options;

	$from_meta = get_post_meta( $post->ID, '_ppp_share_on_publish', true );
	$from_post = isset( $_POST['_ppp_share_on_publish'] );

	if ( empty( $from_meta ) && empty( $from_post ) ) {
		return;
	}

	// Determine if we're seeing the share on publish in meta or $_POST
	if ( $from_meta && !$from_post ) {
		$ppp_share_on_publish_text = get_post_meta( $post->ID, '_ppp_share_on_publish_text', true );
		$use_media = get_post_meta( $post->ID, '_ppp_share_on_publish_include_image', true );
	} else {
		$ppp_share_on_publish_text = isset( $_POST['_ppp_share_on_publish_text'] ) ? $_POST['_ppp_share_on_publish_text'] : '';
		$use_media = isset( $_POST['_ppp_share_on_publish_include_image'] ) ? $_POST['_ppp_share_on_publish_include_image'] : false;
	}

	$share_content = ( !empty( $ppp_share_on_publish_text ) ) ? $ppp_share_on_publish_text : ppp_tw_generate_share_content( $post->ID, null, false );
	$name = 'sharedate_0_' . $post->ID;
	$media = ppp_post_has_media( $post->ID, 'tw', $use_media );
	$share_link = ppp_generate_link( $post->ID, $name, true );

	$status['twitter'] = ppp_send_tweet( $share_content . ' ' . $share_link, $post->ID, $media );

	if ( isset( $ppp_options['enable_debug'] ) && $ppp_options['enable_debug'] == '1' ) {
		update_post_meta( $post->ID, '_ppp-' . $name . '-status', $status );
	}
}
add_action( 'ppp_share_on_publish', 'ppp_tw_share_on_publish', 10, 3 );
