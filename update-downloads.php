<?php
// Update only the published Downloads & Installation page.
$post = get_post( 16 );
if ( ! $post || 'publish' !== $post->post_status ) {
	throw new RuntimeException( 'Expected published Downloads page not found.' );
}
$content = file_get_contents( '/tmp/thessley-site/downloads.html' );
if ( false === $content || false === strpos( $content, 'td-copy' ) || false === strpos( $content, 'navigator.clipboard' ) ) {
	throw new RuntimeException( 'Downloads content missing the copy controls.' );
}
$result = wp_update_post(
	array(
		'ID'           => 16,
		'post_content' => $content,
		'post_status'  => 'publish',
	),
	true
);
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}
echo "Updated Downloads & Installation page with accessible command copy buttons.\n";
