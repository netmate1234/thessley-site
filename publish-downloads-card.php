<?php
/**
 * Add the Windows 11 Pro office-client card to the Downloads page.
 * Idempotent: does nothing if the link to page 125 already exists.
 */

$downloads = get_post( 16 );
if ( ! $downloads ) {
	throw new RuntimeException( 'Downloads page not found.' );
}

$content = $downloads->post_content;
if ( false !== strpos( $content, 'page_id=125' ) ) {
	echo "Downloads already links to the office-client guide.\n";
	return;
}

$anchor = strpos( $content, 'page_id=43' );
if ( false === $anchor ) {
	throw new RuntimeException( 'Could not find the Windows 11 card.' );
}
$close = strpos( $content, '</div>', $anchor );
if ( false === $close ) {
	throw new RuntimeException( 'Could not find the end of the Windows 11 card.' );
}
$close += strlen( '</div>' );

$card = "\n    " . implode( "\n    ", array(
	'<div class="td-card">',
	'  <h3>Windows 11 Pro · office client</h3>',
	'  <p>Clean Pro install for the office logon test: UEFI with TPM 2.0, domain join, OpenSSH with key-only sign-in, and the Office PC Users logon model.</p>',
	'  <a class="td-download" href="?page_id=125">Windows 11 Pro installation guide</a>',
	'</div>',
) );

$updated = substr( $content, 0, $close ) . $card . substr( $content, $close );

$result = wp_update_post(
	array(
		'ID'           => 16,
		'post_content' => wp_slash( $updated ),
	),
	true
);
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

echo "Downloads page updated with the office-client card.\n";
