<?php
/**
 * RCMI theme functions.
 *
 * @package rcmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'rcmi_setup' ) ) {
	/**
	 * Theme supports and pattern registration.
	 */
	function rcmi_setup() {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo' );
		add_theme_support( 'automatic-feed-links' );

		register_nav_menus( array(
			'primary' => __( 'Primary Navigation', 'rcmi' ),
		) );

		// Register block patterns by category.
		$pattern_categories = array(
			'rcmi-hero'      => array( 'label' => __( 'RCMI Hero', 'rcmi' ) ),
			'rcmi-stats'     => array( 'label' => __( 'RCMI Stats', 'rcmi' ) ),
			'rcmi-sections'  => array( 'label' => __( 'RCMI Sections', 'rcmi' ) ),
			'rcmi-cards'     => array( 'label' => __( 'RCMI Cards', 'rcmi' ) ),
			'rcmi-quote'     => array( 'label' => __( 'RCMI Quote', 'rcmi' ) ),
			'rcmi-roles'     => array( 'label' => __( 'RCMI Role Selector', 'rcmi' ) ),
			'rcmi-footer'    => array( 'label' => __( 'RCMI Footer CTA', 'rcmi' ) ),
		);

		foreach ( $pattern_categories as $slug => $args ) {
			register_block_pattern_category( $slug, $args );
		}
	}
}
add_action( 'after_setup_theme', 'rcmi_setup' );

function rcmi_editor_font_family_settings( $settings ) {
	$settings['fontFamilies'] = array(
		array(
			'name'      => __( 'Display (League Gothic)', 'rcmi' ),
			'slug'      => 'display',
			'fontFamily' => "'League Gothic', 'Arial Narrow', sans-serif",
		),
		array(
			'name'      => __( 'Body (Source Sans 3)', 'rcmi' ),
			'slug'      => 'body',
			'fontFamily' => "'Source Sans 3', -apple-system, BlinkMacSystemFont, sans-serif",
		),
		array(
			'name'      => __( 'Serif (Crimson Pro)', 'rcmi' ),
			'slug'      => 'serif',
			'fontFamily' => "'Crimson Pro', Georgia, serif",
		),
	);

	return $settings;
}
add_filter( 'block_editor_settings_all', 'rcmi_editor_font_family_settings' );

/**
 * Enqueue front-end and editor styles + scripts.
 */
function rcmi_assets() {
	$ver = rcmi_asset_version( get_template_directory() . '/assets/css/rcmi.css' );

	// Google Fonts: League Gothic, Source Sans 3, Crimson Pro.
	wp_enqueue_style(
		'rcmi-fonts',
		'https://fonts.googleapis.com/css2?family=League+Gothic&family=Source+Sans+3:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap',
		array(),
		null
	);

	wp_enqueue_style( 'rcmi-style', get_template_directory_uri() . '/assets/css/rcmi.css', array( 'rcmi-fonts' ), $ver );

	wp_enqueue_script( 'rcmi-nav', get_template_directory_uri() . '/assets/js/nav.js', array(), rcmi_asset_version( get_template_directory() . '/assets/js/nav.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'rcmi_assets' );

/**
 * Enqueue editor styles — loaded ONLY into the editor canvas iframe,
 * not the WordPress admin UI (sidebar, toolbar, etc.).
 *
 * add_editor_style() scopes styles to the editor iframe in WP 6.7+.
 * Google Fonts are loaded via enqueue_block_editor_assets (admin-only hook).
 */
function rcmi_editor_assets() {
	// Google Fonts — only in the editor, not the frontend.
	wp_enqueue_style(
		'rcmi-fonts-editor',
		'https://fonts.googleapis.com/css2?family=League+Gothic&family=Source+Sans+3:ital,wght@0,300;0,400;0,600;0,700;1,400&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap',
		array(),
		null
	);

	// Theme CSS + editor CSS — loaded into the editor iframe only.
	// add_editor_style() processes these and injects them into the canvas,
	// keeping the admin UI (sidebar/toolbar) unaffected.
	add_editor_style( 'assets/css/rcmi.css' );
	add_editor_style( 'assets/css/editor.css' );
}
add_action( 'enqueue_block_editor_assets', 'rcmi_editor_assets' );

/**
 * Get a file mtime as a cache-busting version string.
 *
 * @param string $path Absolute filesystem path.
 * @return string
 */
function rcmi_asset_version( $path ) {
	return file_exists( $path ) ? (string) filemtime( $path ) : '1';
}

/**
 * Add body class for root styling context.
 *
 * @param array $classes Existing classes.
 * @return array
 */
function rcmi_body_class( $classes ) {
	$classes[] = 'rcmi-theme';
	return $classes;
}
add_filter( 'body_class', 'rcmi_body_class' );

// ============================================================================
// GitHub-based auto-update system (commit-based, no tags required)
// Checks the latest commit on the main branch and surfaces updates in
// WP Admin → Appearance → Themes as native "Update now" links.
// No third-party plugins needed — same approach as the rcmi-toolkit plugin.
// ============================================================================

define( 'RCMI_THEME_GITHUB_USER', 'andy741231' );
define( 'RCMI_THEME_GITHUB_REPO', 'rcmi-theme' );

/**
 * Fetch the latest commit info from the GitHub API.
 * Cached for 6 hours in a transient to avoid rate-limiting.
 *
 * @return array|false Commit data or false on failure.
 */
function rcmi_theme_get_github_commit() {
	$cache = get_transient( 'rcmi_theme_github_commit' );
	if ( false !== $cache ) {
		return $cache;
	}

	$url = sprintf(
		'https://api.github.com/repos/%s/%s/commits/main',
		RCMI_THEME_GITHUB_USER,
		RCMI_THEME_GITHUB_REPO
	);

	$response = wp_remote_get( $url, array(
		'headers' => array( 'Accept' => 'application/vnd.github.v3+json' ),
		'timeout' => 10,
	) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		set_transient( 'rcmi_theme_github_commit', false, 30 * MINUTE_IN_SECONDS );
		return false;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['sha'] ) ) {
		set_transient( 'rcmi_theme_github_commit', false, 30 * MINUTE_IN_SECONDS );
		return false;
	}

	$sha       = $body['sha'];
	$short_sha = substr( $sha, 0, 7 );
	$commit    = $body['commit'] ?? array();
	$message   = $commit['message'] ?? '';
	$date      = $commit['committer']['date'] ?? '';
	$html_url  = $body['html_url'] ?? '';

	$download_url = sprintf(
		'https://codeload.github.com/%s/%s/zip/refs/heads/main',
		RCMI_THEME_GITHUB_USER,
		RCMI_THEME_GITHUB_REPO
	);

	$data = array(
		'sha'          => $sha,
		'short_sha'    => $short_sha,
		'message'      => $message,
		'date'         => $date,
		'html_url'     => $html_url,
		'download_url' => $download_url,
	);

	set_transient( 'rcmi_theme_github_commit', $data, 6 * HOUR_IN_SECONDS );
	return $data;
}

/**
 * Get the commit SHA that is currently installed.
 *
 * @return string Installed commit SHA or version string.
 */
function rcmi_theme_get_installed_sha() {
	$sha = get_option( 'rcmi_theme_installed_sha' );
	if ( ! empty( $sha ) ) {
		return $sha;
	}
	// Backward compat: use theme version on first check.
	return wp_get_theme()->get( 'Version' );
}

/**
 * Inject update data into the WP update transient for themes.
 *
 * @param object $transient The update_themes transient.
 * @return object
 */
function rcmi_theme_check_for_updates( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$commit = rcmi_theme_get_github_commit();
	if ( ! $commit ) {
		return $transient;
	}

	$installed_sha = rcmi_theme_get_installed_sha();
	if ( $commit['sha'] === $installed_sha ) {
		return $transient;
	}

	$theme_slug = get_template();

	$update = array(
		'theme'       => $theme_slug,
		'new_version' => $commit['short_sha'],
		'url'         => $commit['html_url'],
		'package'     => $commit['download_url'],
	);

	$transient->response[ $theme_slug ] = $update;

	return $transient;
}
add_filter( 'pre_set_site_transient_update_themes', 'rcmi_theme_check_for_updates' );

/**
 * Post-install cleanup: rename the GitHub ZIP's top-level folder
 * (which is "rcmi-theme-<hash>") back to "rcmi" so WordPress
 * doesn't end up with two theme folders.
 * Also records the installed commit SHA.
 *
 * @param bool   $response    Install response.
 * @param array  $hook_extra  Extra arguments.
 * @param array  $result      Installation result data.
 * @return array
 */
function rcmi_theme_post_install_rename( $response, $hook_extra, $result ) {
	if ( ! isset( $hook_extra['theme'] ) ) {
		return $result;
	}
	if ( false === strpos( $hook_extra['theme'], 'rcmi' ) ) {
		return $result;
	}

	$expected = 'rcmi';
	$actual   = basename( $result['destination'] );

	if ( $expected !== $actual ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$new_destination = dirname( $result['destination'] ) . '/' . $expected;

		// If the old theme directory still exists (common on Windows where
		// locked files prevent deletion), remove it before renaming.
		if ( $wp_filesystem && $wp_filesystem->exists( $new_destination ) ) {
			$wp_filesystem->delete( $new_destination, true, 'd' );
		}

		// Try rename via WP_Filesystem (handles FTP/SSH methods too).
		$renamed = false;
		if ( $wp_filesystem && $wp_filesystem->move( $result['destination'], $new_destination ) ) {
			$renamed = true;
		} elseif ( @rename( $result['destination'], $new_destination ) ) {
			$renamed = true;
		}

		if ( $renamed ) {
			$result['destination'] = $new_destination;
			$result['destination_name'] = $expected;
		} else {
			// Last resort: recursive copy + delete.
			if ( $wp_filesystem ) {
				$wp_filesystem->delete( $new_destination, true, 'd' );
				copy_dir( $result['destination'], $new_destination );
				$wp_filesystem->delete( $result['destination'], true, 'd' );
				$result['destination'] = $new_destination;
				$result['destination_name'] = $expected;
			}
		}
	}

	$commit = rcmi_theme_get_github_commit();
	if ( $commit && ! empty( $commit['sha'] ) ) {
		update_option( 'rcmi_theme_installed_sha', $commit['sha'] );
	}

	delete_transient( 'rcmi_theme_github_commit' );

	return $result;
}
add_filter( 'upgrader_post_install', 'rcmi_theme_post_install_rename', 10, 3 );

/**
 * Force a re-check of updates (clears the transient cache).
 * Hooked to admin_init so the ?rcmi_theme_check_updates=1 link
 * triggers an immediate GitHub API call.
 */
function rcmi_theme_maybe_refresh_release_cache() {
	if ( isset( $_GET['rcmi_theme_check_updates'] ) ) {
		delete_transient( 'rcmi_theme_github_commit' );
		delete_site_transient( 'update_themes' );
		rcmi_theme_get_github_commit();

		$redirect = remove_query_arg( 'rcmi_theme_check_updates' );
		wp_safe_redirect( $redirect );
		exit;
	}
}
add_action( 'admin_init', 'rcmi_theme_maybe_refresh_release_cache' );

/**
 * Add a "Check for updates" link to the theme's action row on the
 * Themes page. Clicking it forces an immediate GitHub API check.
 *
 * @param array  $links Action links.
 * @param string $file  Theme stylesheet.
 * @return array
 */
function rcmi_theme_add_check_updates_link( $links, $file ) {
	if ( 'rcmi' !== $file ) {
		return $links;
	}
	$url = add_query_arg( 'rcmi_theme_check_updates', '1', admin_url( 'themes.php' ) );
	$check_link = '<a href="' . esc_url( $url ) . '">Check for updates</a>';
	array_unshift( $links, $check_link );
	return $links;
}
add_filter( 'theme_action_links', 'rcmi_theme_add_check_updates_link', 10, 2 );
