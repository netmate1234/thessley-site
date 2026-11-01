<?php
// Publish the AD service installation guide under Downloads (page 16).
$content = file_get_contents( '/tmp/thessley-site/ad-service-install.html' );
if ( false === $content
	|| false === strpos( $content, 'install-windows-service.ps1' )
	|| false === strpos( $content, 'ad.ps1' )
	|| false === strpos( $content, 'navigator.clipboard' ) ) {
	throw new RuntimeException( 'AD service install page content validation failed.' );
}

$parent = get_post( 16 );
if ( ! $parent || 'downloads' !== $parent->post_name ) {
	throw new RuntimeException( 'Expected Downloads page 16 was not found.' );
}

$page = null;
foreach ( get_pages( array( 'parent' => 16, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ) ) ) as $candidate ) {
	if ( 'ad-service-installation' === $candidate->post_name ) {
		$page = $candidate;
		break;
	}
}
if ( $page ) {
	$result = wp_update_post( array(
		'ID'           => $page->ID,
		'post_title'   => 'AD Service Installation',
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
	), true );
} else {
	$result = wp_insert_post( array(
		'post_title'   => 'AD Service Installation',
		'post_name'    => 'ad-service-installation',
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_parent'  => 16,
	), true );
}
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}
printf( "Published AD service installation guide (page %d).\n", (int) $result );
