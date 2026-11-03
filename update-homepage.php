<?php
// Update only the published Thessley project overview on WP-Opsec.
$post = get_post( 6 );
if ( ! $post || 'publish' !== $post->post_status ) {
	throw new RuntimeException( 'Expected published Thessley homepage not found.' );
}
$content = file_get_contents( '/tmp/thessley-site/homepage.html' );
if ( false === $content || false === strpos( $content, 'APT repository hosted on the Orange Pi 5' ) ) {
	throw new RuntimeException( 'Homepage content is missing the Ubuntu APT repository details.' );
}
$result = wp_update_post(
	array(
		'ID'           => 6,
		'post_content' => $content,
		'post_status'  => 'publish',
	),
	true
);
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}
echo "Updated the Thessley project overview with Ubuntu APT repository details.\n";
