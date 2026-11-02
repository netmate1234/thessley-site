<?php
/**
 * Publish the Windows 11 Pro office-client guide under Downloads.
 *
 * Source of truth: site-content/windows-pro-install.html in the wp_opsec repo.
 * Run with: sudo -u www-data wp --path=/var/www/wp-dev eval-file publish-windows-pro-install.php
 */

$content = file_get_contents( '/tmp/thessley-site/windows-pro-install.html' );
if ( false === $content
	|| false === strpos( $content, 'Add-WindowsCapability' )
	|| false === strpos( $content, 'SeNetworkLogonRight' )
	|| false === strpos( $content, 'Office PC Users' )
	|| false === strpos( $content, 'thessley-downloads' ) ) {
	throw new RuntimeException( 'Windows Pro installation page content validation failed.' );
}

$parent = get_page_by_path( 'downloads' );
if ( ! $parent ) {
	throw new RuntimeException( 'Could not find the Downloads parent page.' );
}

$page = get_page_by_path( 'downloads/windows-pro-installation', OBJECT, 'page' );
if ( ! $page ) {
	$found = get_posts(
		array(
			'post_type'      => 'page',
			'name'           => 'windows-pro-installation',
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);
	$page = $found ? $found[0] : null;
}

$data = array(
	'post_title'   => 'Windows 11 Pro Office Client',
	'post_name'    => 'windows-pro-installation',
	'post_content' => wp_slash( $content ),
	'post_status'  => 'publish',
	'post_type'    => 'page',
	'post_parent'  => (int) $parent->ID,
);

if ( $page ) {
	$data['ID'] = (int) $page->ID;
	$result     = wp_update_post( $data, true );
	$verb       = 'Updated';
} else {
	$result = wp_insert_post( $data, true );
	$verb   = 'Published';
}

if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

printf( "%s Windows Pro office-client guide (page %d).\n", $verb, (int) $result );
