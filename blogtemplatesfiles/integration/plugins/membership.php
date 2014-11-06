<?php

function nbt_add_membership_caps( $template, $user_id ) {
	if( class_exists( 'membershipadmin' ) ) {
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
	}
}
add_action( 'blog_templates-copy-options', 'nbt_add_membership_caps', 10, 2 );

