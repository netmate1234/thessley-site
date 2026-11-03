<?php
// One-time WordPress CLI update for the two backed-up public presentation pages.
$updates = array(
	6 => array(
		'title'   => 'Thessley Projects',
		'content' => '/tmp/thessley-site/homepage.html',
		'excerpt' => 'Security tools for websites, computers, and sign-in.',
	),
	16 => array(
		'title'   => 'Downloads & Installation',
		'content' => '/tmp/thessley-site/downloads.html',
		'excerpt' => 'Download Thessley project files and follow the installation instructions.',
	),
);

foreach ( $updates as $post_id => $data ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		throw new RuntimeException( 'Expected published page not found: ' . $post_id );
	}
	$content = file_get_contents( $data['content'] );
	if ( false === $content || false === strpos( $content, 'thessley-' ) ) {
		throw new RuntimeException( 'Page content missing or unexpected: ' . $data['content'] );
	}
	$result = wp_update_post(
		array(
			'ID'           => $post_id,
			'post_title'   => $data['title'],
			'post_content' => $content,
			'post_excerpt' => $data['excerpt'],
			'post_status'  => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	echo 'Updated published page ' . $post_id . ': ' . $data['title'] . PHP_EOL;
}

update_option( 'blogname', 'Thessley Security Projects' );
update_option( 'blogdescription', 'Security tools for websites, computers, and sign-in.' );
echo 'Updated site title and tagline.' . PHP_EOL;
