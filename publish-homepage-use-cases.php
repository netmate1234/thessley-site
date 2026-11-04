<?php
// Publish the use-case section to the Thessley Projects page, preserving a
// timestamped copy of the current page content before updating it.
$post_id = 6;
$post    = get_post( $post_id );

if ( ! $post || 'publish' !== $post->post_status ) {
	throw new RuntimeException( 'Expected published Thessley homepage not found.' );
}

$content_path = '/tmp/thessley-site/homepage.html';
$content      = file_get_contents( $content_path );

if ( false === $content || false === strpos( $content, 'Use cases across your systems' ) ) {
	throw new RuntimeException( 'Homepage source is missing the use-case section.' );
}

$backup_path = '/tmp/homepage-before-use-cases-' . gmdate( 'Ymd-His' ) . '.html';
if ( false === file_put_contents( $backup_path, $post->post_content, LOCK_EX ) ) {
	throw new RuntimeException( 'Could not back up current homepage content.' );
}

$result = wp_update_post(
	array(
		'ID'           => $post_id,
		'post_content' => $content,
		'post_status'  => 'publish',
	),
	true
);

if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

echo 'Published Thessley homepage use cases; backup: ' . $backup_path . PHP_EOL;
