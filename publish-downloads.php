<?php
// Publish only the Thessley Downloads portal. Platform install pages are separate.
$content = file_get_contents('/tmp/thessley-site/downloads.html');
if (false === $content
	|| false === strpos($content, 'thessley-passkey-android-0.1.18.apk')
	|| false === strpos($content, 'navigator.clipboard')
	|| false !== strpos($content, '__LINUX_PAGE_ID__')
	|| false !== strpos($content, '__WINDOWS_PAGE_ID__')) {
	throw new RuntimeException('Downloads page validation failed.');
}

$page = get_post(16);
if (!$page || 'downloads' !== $page->post_name || 'page' !== $page->post_type) {
	throw new RuntimeException('Expected Downloads page 16 was not found.');
}

$result = wp_update_post(
	array(
		'ID'           => 16,
		'post_title'   => 'Thessley Downloads',
		'post_content' => wp_slash($content),
		'post_status'  => 'publish',
	),
	true
);
if (is_wp_error($result)) {
	throw new RuntimeException($result->get_error_message());
}
printf("Published Downloads page (%d).\n", (int) $result);
