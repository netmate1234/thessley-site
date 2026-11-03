<?php
// Publish a downloads portal and its platform-specific installation pages.
$root = '/tmp/thessley-site/';
$pages = array(
	'linux-installation' => array(
		'title' => 'Linux Installation',
		'file'  => 'linux-install.html',
	),
	'windows-installation' => array(
		'title' => 'Windows Installation',
		'file'  => 'windows-install.html',
	),
);
$ids = array();
foreach ( $pages as $slug => $page ) {
	$content = file_get_contents( $root . $page['file'] );
	if ( false === $content || false === strpos( $content, 'navigator.clipboard' ) || false === strpos( $content, '<pre><code>' ) ) {
		throw new RuntimeException( 'Missing install page content or copy controls for ' . $slug );
	}
	// These installation guides are children of the Downloads page (ID 16).
	// Query by parent + slug so repeat publishes update the canonical child pages
	// instead of creating linux-installation-2, -3, and so on.
	$existing = null;
	foreach ( get_pages( array( 'parent' => 16, 'post_status' => array( 'publish', 'draft', 'private', 'pending' ) ) ) as $candidate ) {
		if ( $candidate->post_name === $slug ) {
			$existing = $candidate;
			break;
		}
	}
	$data     = array(
		'post_title'   => $page['title'],
		'post_name'    => $slug,
		'post_content' => wp_slash( $content ),
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_parent'  => 16,
		'post_excerpt' => 'Guided one-line installer and alternative setup instructions.',
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
		$id         = wp_update_post( $data, true );
	} else {
		$id = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$ids[ $slug ] = (int) $id;
}

$portal = file_get_contents( $root . 'downloads.html' );
$portal = str_replace( '__LINUX_PAGE_ID__', (string) $ids['linux-installation'], $portal );
$portal = str_replace( '__WINDOWS_PAGE_ID__', (string) $ids['windows-installation'], $portal );
if ( false === strpos( $portal, 'navigator.clipboard' ) || false !== strpos( $portal, '__LINUX_PAGE_ID__' ) || false !== strpos( $portal, '__WINDOWS_PAGE_ID__' ) ) {
	throw new RuntimeException( 'Downloads portal validation failed.' );
}
$updated = wp_update_post(
	array(
		'ID'           => 16,
		'post_title'   => 'Thessley Downloads',
		'post_name'    => 'downloads',
		'post_excerpt' => 'Downloads and guided installation for Thessley security projects.',
		'post_content' => wp_slash( $portal ),
		'post_status'  => 'publish',
	),
	true
);
if ( is_wp_error( $updated ) ) {
	throw new RuntimeException( $updated->get_error_message() );
}
printf( "Published downloads portal (16), Linux installation (%d), Windows installation (%d).\n", $ids['linux-installation'], $ids['windows-installation'] );
