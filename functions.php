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
