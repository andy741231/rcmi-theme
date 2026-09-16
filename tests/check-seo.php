<?php
$rcmi_seo_failures = array();

function rcmi_seo_check( $condition, $message ) {
	global $rcmi_seo_failures;
	if ( ! $condition ) {
		$rcmi_seo_failures[] = $message;
	}
}

function rcmi_seo_fetch( $path ) {
	$response = wp_remote_get( home_url( $path ), array( 'headers' => array( 'DNT' => '1' ) ) );
	if ( is_wp_error( $response ) ) {
		return array( 'code' => 0, 'body' => '' );
	}
	return array(
		'code' => wp_remote_retrieve_response_code( $response ),
		'body' => wp_remote_retrieve_body( $response ),
	);
}

function rcmi_seo_canonicals( $body ) {
	$hrefs = array();
	if ( preg_match_all( '/<link[^>]*rel=["\']canonical["\'][^>]*>/i', $body, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			if ( preg_match( '/href=["\']([^"\']+)["\']/i', $tag, $m ) ) {
				$hrefs[] = html_entity_decode( $m[1] );
			}
		}
	}
	return $hrefs;
}

function rcmi_seo_robots_content( $body ) {
	if ( preg_match( '/<meta[^>]+name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']/i', $body, $m )
		|| preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*name=["\']robots["\']/i', $body, $m ) ) {
		return $m[1];
	}
	return '';
}

function rcmi_seo_og_url( $body ) {
	if ( preg_match( '/<meta[^>]+property=["\']og:url["\'][^>]*content=["\']([^"\']*)["\']/i', $body, $m ) ) {
		return html_entity_decode( $m[1] );
	}
	return '';
}

$r = rcmi_seo_fetch( '/tickets/' );
rcmi_seo_check( 200 === $r['code'], '/tickets/ should return 200, got ' . $r['code'] );
$robots = rcmi_seo_robots_content( $r['body'] );
rcmi_seo_check( false !== strpos( $robots, 'noindex' ), '/tickets/ robots should contain noindex, got "' . $robots . '"' );
rcmi_seo_check( false !== strpos( $robots, 'follow' ), '/tickets/ robots should contain follow, got "' . $robots . '"' );
rcmi_seo_check( in_array( home_url( '/tickets/' ), rcmi_seo_canonicals( $r['body'] ), true ), '/tickets/ should have canonical ' . home_url( '/tickets/' ) );

$r = rcmi_seo_fetch( '/does-not-exist-rcmi-seo-test/' );
rcmi_seo_check( 404 === $r['code'], '/does-not-exist-rcmi-seo-test/ should return 404, got ' . $r['code'] );
$robots = rcmi_seo_robots_content( $r['body'] );
rcmi_seo_check( false !== strpos( $robots, 'noindex' ), '404 robots should contain noindex, got "' . $robots . '"' );
rcmi_seo_check( false !== strpos( $robots, 'follow' ), '404 robots should contain follow, got "' . $robots . '"' );
rcmi_seo_check( false === strpos( $r['body'], 'og:url' ), '404 should not emit og:url' );
rcmi_seo_check( false === strpos( $r['body'], 'twitter:card' ), '404 should not emit twitter:card' );

$r = rcmi_seo_fetch( '/stories/' );
rcmi_seo_check( 200 === $r['code'], '/stories/ should return 200, got ' . $r['code'] );
$canonicals = rcmi_seo_canonicals( $r['body'] );
rcmi_seo_check( 1 === count( $canonicals ), '/stories/ should have exactly one canonical, got ' . count( $canonicals ) );
rcmi_seo_check( isset( $canonicals[0] ) && home_url( '/stories/' ) === $canonicals[0], '/stories/ canonical should be ' . home_url( '/stories/' ) . ', got ' . ( isset( $canonicals[0] ) ? $canonicals[0] : '(none)' ) );
rcmi_seo_check( home_url( '/stories/' ) === rcmi_seo_og_url( $r['body'] ), '/stories/ og:url should be ' . home_url( '/stories/' ) . ', got ' . rcmi_seo_og_url( $r['body'] ) );

$r = rcmi_seo_fetch( '/stories/category/events/' );
if ( 200 === $r['code'] ) {
	$canonicals = rcmi_seo_canonicals( $r['body'] );
	rcmi_seo_check( 1 === count( $canonicals ), '/stories/category/events/ should have exactly one canonical, got ' . count( $canonicals ) );
	rcmi_seo_check( isset( $canonicals[0] ) && home_url( '/stories/category/events/' ) === $canonicals[0], '/stories/category/events/ canonical should be ' . home_url( '/stories/category/events/' ) . ', got ' . ( isset( $canonicals[0] ) ? $canonicals[0] : '(none)' ) );
	rcmi_seo_check( home_url( '/stories/category/events/' ) === rcmi_seo_og_url( $r['body'] ), '/stories/category/events/ og:url should be ' . home_url( '/stories/category/events/' ) . ', got ' . rcmi_seo_og_url( $r['body'] ) );
}

$r = rcmi_seo_fetch( '/stories/author/andy/' );
if ( 200 === $r['code'] ) {
	$robots = rcmi_seo_robots_content( $r['body'] );
	rcmi_seo_check( false !== strpos( $robots, 'noindex' ), '/stories/author/andy/ robots should contain noindex, got "' . $robots . '"' );
	rcmi_seo_check( false !== strpos( $robots, 'follow' ), '/stories/author/andy/ robots should contain follow, got "' . $robots . '"' );
	rcmi_seo_check( 0 === count( rcmi_seo_canonicals( $r['body'] ) ), '/stories/author/andy/ should have no canonical tags' );
}

$r = rcmi_seo_fetch( '/wp-sitemap.xml' );
rcmi_seo_check( 200 === $r['code'], '/wp-sitemap.xml should return 200, got ' . $r['code'] );
rcmi_seo_check( false === strpos( $r['body'], 'wp-sitemap-users' ), 'users sitemap should be absent from /wp-sitemap.xml' );

$r = rcmi_seo_fetch( '/wp-sitemap-posts-page-1.xml' );
rcmi_seo_check( 200 === $r['code'], '/wp-sitemap-posts-page-1.xml should return 200, got ' . $r['code'] );
rcmi_seo_check( false === strpos( $r['body'], home_url( '/tickets/' ) ), 'tickets URL should be absent from the page sitemap' );

if ( $rcmi_seo_failures ) {
	echo "RCMI SEO checks FAILED:\n";
	foreach ( $rcmi_seo_failures as $failure ) {
		echo ' - ' . $failure . "\n";
	}
	exit( 1 );
}
echo "RCMI SEO checks: all passed.\n";
