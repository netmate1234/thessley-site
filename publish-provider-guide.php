<?php
// Update only the existing Thessley Passkey protocol presentation page.
$content = file_get_contents( '/tmp/thessley-site/passkey-protocol.html' );
if ( false === $content || false === strpos( $content, 'Build a QR login provider' ) || false === strpos( $content, 'verifyWebsiteResponse' ) ) {
	throw new RuntimeException( 'Provider guide content is missing.' );
}
$page = get_post( 45 );
if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
	throw new RuntimeException( 'Expected published presentation page 45 was not found.' );
}
$result = wp_update_post(
	array(
		'ID'           => 45,
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
	),
	true
);
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}
printf( "Updated provider guide on page %d.\n", $result );
