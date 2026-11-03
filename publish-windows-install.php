<?php
$content = file_get_contents( '/tmp/thessley-site/windows-install.html' );
if ( false === $content || false === strpos( $content, 'install.ps1 | iex' ) || false === strpos( $content, 'navigator.clipboard' ) ) {
	throw new RuntimeException( 'Windows install page content validation failed.' );
}

$page = null;
foreach ( get_pages( array( 'parent' => 16, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ) ) ) as $candidate ) {
	if ( 'windows-installation' === $candidate->post_name ) {
		$page = $candidate;
		break;
	}
}
if ( ! $page ) {
	throw new RuntimeException( 'Could not find the Windows installation page under Downloads.' );
}

$result = wp_update_post(
	array(
		'ID'           => $page->ID,
		'post_title'   => 'Windows Installation',
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
	),
	true
);
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}
printf( "Published Windows installation guide (page %d).\n", (int) $result );
