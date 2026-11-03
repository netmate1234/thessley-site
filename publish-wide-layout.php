<?php
// Give all custom Thessley project pages the theme's full wide-content layout.
$root = '/tmp/thessley-site/';
$pages = array(
	6  => 'homepage.html',
	16 => 'downloads.html',
	42 => 'linux-install.html',
	43 => 'windows-install.html',
	45 => 'passkey-protocol.html',
);
foreach ( $pages as $id => $file ) {
	$post = get_post( $id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		throw new RuntimeException( 'Expected published page not found: ' . $id );
	}
	$content = file_get_contents( $root . $file );
	if ( false === $content || false === strpos( $content, 'alignwide' ) ) {
		throw new RuntimeException( 'Wide layout class missing from ' . $file );
	}
	$content = str_replace( '__PASSKEY_PRESENTATION_ID__', '45', $content );
	$result  = wp_update_post(
		array(
			'ID'           => $id,
			'post_content' => wp_slash( $content ),
			'post_status'  => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	echo 'Applied wide layout to page ' . $id . ': ' . $file . PHP_EOL;
}
