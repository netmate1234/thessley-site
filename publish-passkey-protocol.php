<?php
// Publish the visual protocol presentation and add entry points from the overview and downloads pages.
$root = '/tmp/thessley-site/';
$content = file_get_contents( $root . 'passkey-protocol.html' );
if ( false === $content || false === strpos( $content, 'pp-approval-title' ) && false === strpos( $content, 'Your phone signs. The service decides.' ) ) {
	throw new RuntimeException( 'Protocol presentation content is missing.' );
}
$slug     = 'how-thessley-passkeys-work';
$existing = get_page_by_path( $slug, OBJECT, 'page' );
$data     = array(
	'post_title'   => 'How Thessley Passkeys Work',
	'post_name'    => $slug,
	'post_content' => wp_slash( $content ),
	'post_excerpt' => 'Visual guide to enrollment, QR requests, signed approvals, and protocol boundaries.',
	'post_status'  => 'publish',
	'post_type'    => 'page',
);
if ( $existing ) {
	$data['ID'] = $existing->ID;
	$page_id    = wp_update_post( $data, true );
} else {
	$page_id = wp_insert_post( $data, true );
}
if ( is_wp_error( $page_id ) ) {
	throw new RuntimeException( $page_id->get_error_message() );
}

foreach ( array( 6 => 'homepage.html', 16 => 'downloads.html' ) as $id => $file ) {
	$page = get_post( $id );
	if ( ! $page || 'publish' !== $page->post_status ) {
		throw new RuntimeException( 'Expected published entry page not found: ' . $id );
	}
	$entry = file_get_contents( $root . $file );
	if ( false === $entry || false === strpos( $entry, '__PASSKEY_PRESENTATION_ID__' ) ) {
		throw new RuntimeException( 'Presentation link placeholder missing from ' . $file );
	}
	$entry = str_replace( '__PASSKEY_PRESENTATION_ID__', (string) $page_id, $entry );
	$result = wp_update_post(
		array(
			'ID'           => $id,
			'post_content' => wp_slash( $entry ),
			'post_status'  => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
}
printf( "Published protocol presentation (page %d) and linked it from the homepage and downloads portal.\n", $page_id );
