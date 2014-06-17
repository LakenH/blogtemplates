<?php

// Other plugins integrations

function nbt_add_membership_caps( $user_id, $blog_id ) {
	switch_to_blog( $blog_id );
	$user = get_userdata( $user_id );
	$user->add_cap('membershipadmin');
	$user->add_cap('membershipadmindashboard');
	$user->add_cap('membershipadminmembers');
	$user->add_cap('membershipadminlevels');
	$user->add_cap('membershipadminsubscriptions');
	$user->add_cap('membershipadmincoupons');
	$user->add_cap('membershipadminpurchases');
	$user->add_cap('membershipadmincommunications');
	$user->add_cap('membershipadmingroups');
	$user->add_cap('membershipadminpings');
	$user->add_cap('membershipadmingateways');
	$user->add_cap('membershipadminoptions');
	$user->add_cap('membershipadminupdatepermissions');
	update_user_meta( $user_id, 'membership_permissions_updated', 'yes');
	restore_current_blog();
}

function nbt_bp_add_register_scripts() {
	?>
	<script>
		jQuery(document).ready(function($) {
			var bt_selector = $('#blog_template-selection').remove();
			bt_selector.appendTo( $('#blog-details') );
		});
	</script>
	<?php
}

add_action( 'plugins_loaded', 'nbt_appplus_unregister_action' );
function nbt_appplus_unregister_action() {
	if ( class_exists('Appointments' ) ) {
		global $appointments;
		remove_action( 'wpmu_new_blog', array( $appointments, 'new_blog' ), 10, 6 );
	}
}

add_filter( 'blog_template_exclude_settings', 'nbt_popover_remove_install_setting', 10, 1 );
function nbt_popover_remove_install_setting( $query ) {
	$query .= " AND `option_name` != 'popover_installed' ";
	return $query;
}

// Framemarket theme
add_filter( 'framemarket_list_shops', 'nbt_framemarket_list_shops' );
function nbt_framemarket_list_shops( $blogs ) {
	$return = array();

	if ( ! empty( $blogs ) ) {
		$model = nbt_get_model();
		foreach ( $blogs as $blog ) {
			if ( ! $model->is_template( $blog->blog_id ) )
				$return[] = $blog;
		}
	}

	return $return;
}

add_filter( 'blogs_directory_blogs_list', 'nbt_remove_blogs_from_directory' );
function nbt_remove_blogs_from_directory( $blogs ) {
	$model = nbt_get_model();
	$new_blogs = array();
	foreach ( $blogs as $blog ) {
		if ( ! $model->is_template( $blog['blog_id'] ) )
			$new_blogs[] = $blog;
	}
	return $new_blogs;
}

/** AUTOBLOG **/
add_action( 'blog_templates-copy-options', 'nbt_copy_autoblog_feeds' );
function nbt_copy_autoblog_feeds( $template ) {
	global $wpdb;

	// Site ID, blog ID...
	$current_site = get_current_site();
	$current_site_id = $current_site->id;

	$source_blog_id = $template['blog_id'];
	$autoblog_on = false;

	switch_to_blog( $source_blog_id );
	// Is Autoblog activated?
	if ( ! function_exists( 'is_plugin_active' ) )
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	if ( is_plugin_active( 'autoblog/autoblogpremium.php' ) )
		$autoblog_on = true;

	// We'll need this values later
	$source_url = get_site_url( $source_blog_id );
	$source_url_ssl = get_site_url( $source_blog_id, '', 'https' );

	restore_current_blog();

	if ( ! $autoblog_on )
		return;

	// Getting all the feed data for the source blog ID
	$autoblog_table = $wpdb->base_prefix . 'autoblog';
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $autoblog_table WHERE blog_id = %d AND site_id = %d", $source_blog_id, $current_site_id ) );

	if ( ! empty( $results ) ) {
		$current_blog_id = get_current_blog_id();

		$current_url = get_site_url( $current_blog_id );
		$current_url_ssl = get_site_url( $current_blog_id, '', 'https' );

		foreach ( $results as $row ) {
			// Getting the feed metadata
			$feed_meta = maybe_unserialize( $row->feed_meta );

			// We need to replace the source blog URL for the new one
			$feed_meta = str_replace( $source_url, $current_url, $feed_meta );
			$feed_meta = str_replace( $source_url_ssl, $current_url_ssl, $feed_meta );

			$row->feed_meta = maybe_serialize( $feed_meta );

			// Inserting feed for the new blog
			$wpdb->insert(
				$autoblog_table,
				array(
					'site_id' => $current_site_id,
					'blog_id' => $current_blog_id,
					'feed_meta' => $row->feed_meta,
					'active' => $row->active,
					'nextcheck' => $row->nextcheck,
					'lastupdated' => $row->lastupdated
				),
				array( '%d', '%d', '%s', '%d', '%d', '%d' )
			);
		}
	}

}

/** EASY GOOGLE FONTS **/
add_action( 'blog_templates-copy-after_copying', 'nbt_copy_easy_google_fonts_controls', 10, 2 );
function nbt_copy_easy_google_fonts_controls( $template, $destination_blog_id ) {
	global $wpdb;

	if ( ! is_plugin_active( 'easy-google-fonts/easy-google-fonts.php' ) )
		return;

	$source_blog_id = $template['blog_id'];

	if ( ! isset( $template['to_copy']['posts'] ) && get_blog_details( $source_blog_id ) && get_blog_details( $destination_blog_id ) ) {
		switch_to_blog( $source_blog_id );

		$post_query = "SELECT t1.* FROM {$wpdb->posts} t1 ";
		$post_query .= "WHERE t1.post_type = 'tt_font_control'";
		$posts_results = $wpdb->get_results( $post_query );

		$postmeta_query = "SELECT t1.* FROM {$wpdb->postmeta} t1 ";
		$postmeta_query .= "INNER JOIN $wpdb->posts t2 ON t1.post_id = t2.ID WHERE t2.post_type = 'tt_font_control'";
		$postmeta_results = $wpdb->get_results( $postmeta_query );
		
		restore_current_blog();

		switch_to_blog( $destination_blog_id );
		foreach ( $posts_results as $row ) {
            $row = (array)$row;
            $wpdb->insert( $wpdb->posts, $row );
        }

        foreach ( $postmeta_results as $row ) {
            $row = (array)$row;
            $wpdb->insert( $wpdb->postmeta, $row );
        }

        restore_current_blog();

	}
}


/** MARKETPRESS **/
add_action( 'blog_templates-copy-after_copying', 'nbt_set_marketpress_email', 10, 2 );
function nbt_set_marketpress_email( $template, $destination_blog_id ) {

	if ( ! function_exists( 'is_plugin_active' ) )
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	switch_to_blog( $destination_blog_id );
	if ( ! is_plugin_active( 'marketpress/marketpress.php' ) ) {
		restore_current_blog();
		return;
	}
	restore_current_blog();

	$source_blog_id = $template['blog_id'];

	if ( in_array( 'settings', $template['to_copy'] ) && get_blog_details( $source_blog_id ) && get_blog_details( $destination_blog_id ) ) {
		switch_to_blog( $destination_blog_id );
		$marketpress_options = get_option( 'mp_settings', array() );
		$marketpress_options['store_email'] = get_option( 'admin_email' );
		update_option( 'mp_settings', $marketpress_options );
		restore_current_blog();
	}
	
}



/** GRAVITY FORMS **/
/*
 * Rightt now, hooking New Blog Templates into GF is not possible
 * GF overrides the meta values passed to wpmu_create_blog.
 * I submitted a ticket asking about adding a new filter for that
 */

add_action( 'nbt_object_create', 'set_gravity_forms_hooks' );

function set_gravity_forms_hooks( $blog_templates ) {
	add_filter( 'gform_get_form_filter', 'nbt_render_user_registration_form', 15, 2 );
	add_filter( 'gform_user_registration_add_option_section', 'nbt_add_blog_templates_user_registration_option', 15, 3 );
	add_filter( "gform_user_registration_save_config", "nbt_save_multisite_user_registration_config" );
	add_filter( 'gform_site_registration_signup_meta', 'nbt_save_new_blog_meta' );
}

function nbt_save_new_blog_meta( $meta ) {
	if ( isset( $_POST['blog_template' ] ) ) {
		$meta['blog_template'] = absint( $_POST['blog_template'] );
	}
	return $meta;
}

function nbt_add_blog_templates_user_registration_option( $config, $form, $is_validation_error ) {

	$multisite_options = rgar($config['meta'], 'multisite_options');

	?>
		<div id="nbt-integration">
			<h3><?php _e( "New Blog Templates", 'blog_templates' ); ?></h3>
			<div class="margin_vertical_10">
                <label class="left_header"><?php _e( 'Display Templates Selector', 'blog_templates' ); ?></label>
                <input type="checkbox" id="gf_user_registration_multisite_blog_templates" name="gf_user_registration_multisite_blog_templates" value="1" <?php echo rgar( $multisite_options, 'blog_templates' ) ? "checked='checked'" : "" ?> />
            </div>
		</div>
	<?php
}

function nbt_save_multisite_user_registration_config( $config ) {
	$config['meta']['multisite_options']['blog_templates'] = RGForms::post("gf_user_registration_multisite_blog_templates");
	return $config;
}


function nbt_render_user_registration_form( $form_html, $form ) {

	global $blog_templates;

	$config = GFUserData::get_feed( $form['id'] );
	$multisite_options = rgar( $config['meta'], 'multisite_options' );
	if ( isset( $multisite_options['blog_templates'] ) && absint( $multisite_options['blog_templates'] ) ) {
		ob_start();
		$blog_templates->registration_template_selection();
		$nbt_selection = ob_get_clean();
		
		$form_html .= $nbt_selection;

		$form_id = $form['id'];
		ob_start();
		// Adding some Javascript
		?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					var submit_button = $( '#gform_submit_button_' + <?php echo $form_id; ?> );

					$('#blog_template-selection').insertBefore( submit_button );
				});
			</script>
		<?php
		$form_html .= ob_get_clean();

	}

	return $form_html;
}

