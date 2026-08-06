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
			'footer'  => __( 'Footer Navigation', 'rcmi' ),
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

		// Editor styles — must be registered during after_setup_theme so the
		// Site Editor's iframe picks them up. Calling add_editor_style() during
		// enqueue_block_editor_assets is too late in the lifecycle for WP 7.0+.
		add_editor_style( 'assets/css/rcmi.css' );
		add_editor_style( 'assets/css/editor.css' );
	}
}
add_action( 'after_setup_theme', 'rcmi_setup' );

/**
 * Custom nav walkers for the dynamic site header block.
 */
require_once get_template_directory() . '/inc/class-rcmi-nav-walker.php';
require_once get_template_directory() . '/inc/class-rcmi-mobile-nav-walker.php';
require_once get_template_directory() . '/inc/class-rcmi-footer-walker.php';

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

	// Theme CSS + editor CSS are registered via add_editor_style() in
	// rcmi_setup() (after_setup_theme) — that's the correct hook for the
	// Site Editor's iframe to pick them up in WP 7.0+.

	// Editor-side registration for the rcmi/site-header and rcmi/site-footer
	// dynamic blocks so the Site Editor shows live ServerSideRender previews
	// with InspectorControls (color pickers + style toggles).
	wp_enqueue_script(
		'rcmi-site-header-block',
		get_template_directory_uri() . '/assets/js/site-header-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		rcmi_asset_version( get_template_directory() . '/assets/js/site-header-block.js' ),
		true
	);
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
// Dynamic site header block (rcmi/site-header)
// Renders the site header + mobile nav from the "primary" WordPress nav menu
// (managed via Appearance > Menus). Editors add/remove/reorder items there;
// the custom walkers preserve the .nav-links / .dropdown / .mobile-nav markup
// that the theme CSS expects. The logo, CTA buttons, and toggle are rendered
// by the block (not the menu) since they are header chrome, not navigation.
// ============================================================================

/**
 * Register the rcmi/site-header dynamic block.
 */
function rcmi_register_site_header_block() {
	register_block_type( 'rcmi/site-header', array(
		'attributes'      => array(
			'backgroundColor' => array( 'type' => 'string', 'default' => '' ),
			'textColor'       => array( 'type' => 'string', 'default' => '' ),
			'accentColor'     => array( 'type' => 'string', 'default' => '' ),
			'ctaBgColor'      => array( 'type' => 'string', 'default' => '' ),
			'ctaTextColor'    => array( 'type' => 'string', 'default' => '' ),
			'sticky'          => array( 'type' => 'boolean', 'default' => true ),
			'transparent'     => array( 'type' => 'boolean', 'default' => false ),
			'logoMark'        => array( 'type' => 'string', 'default' => 'RC' ),
			'logoText'        => array( 'type' => 'string', 'default' => 'RCMI' ),
			'logoSub'         => array( 'type' => 'string', 'default' => 'Research Capacity & Mentoring Institute' ),
			'logoImageId'     => array( 'type' => 'number', 'default' => 0 ),
			'logoImageUrl'    => array( 'type' => 'string', 'default' => '' ),
			'buttons'         => array(
				'type'    => 'array',
				'default' => array(
					array( 'text' => 'Request Support',   'link' => '/#start',              'style' => 'outline' ),
					array( 'text' => 'Explore Research',  'link' => '/cores/#investigator', 'style' => 'primary' ),
				),
			),
		),
		'render_callback' => 'rcmi_render_site_header_block',
	) );
}
add_action( 'init', 'rcmi_register_site_header_block' );

/**
 * Default menu items used to seed the Primary Menu on theme activation and
 * to render a fallback when no menu is assigned to the "primary" location.
 *
 * @return array
 */
function rcmi_default_menu_items() {
	return array(
		array(
			'title' => 'Home',
			'url'   => home_url( '/' ),
		),
		array(
			'title'    => 'About',
			'url'      => home_url( '/about/' ),
			'children' => array(
				array( 'title' => 'Mission',       'url' => home_url( '/about/#mission' ) ),
				array( 'title' => 'Why We Exist',  'url' => home_url( '/about/#why' ) ),
			),
		),
		array(
			'title'    => 'Core',
			'url'      => home_url( '/cores/' ),
			'children' => array(
				array( 'title' => 'Admin Core',                'url' => home_url( '/cores/#admin' ) ),
				array( 'title' => 'Investigator Core',         'url' => home_url( '/cores/#investigator' ) ),
				array( 'title' => 'Community Engagement Core', 'url' => home_url( '/cores/#community' ) ),
				array( 'title' => 'Research Core',             'url' => home_url( '/cores/#research' ) ),
			),
		),
		array(
			'title' => 'The Journey',
			'url'   => home_url( '/journey/' ),
		),
		array(
			'title'    => 'Resource',
			'url'      => home_url( '/dashboard/' ),
			'children' => array(
				array( 'title' => 'Dashboard',          'url' => home_url( '/dashboard/' ) ),
				array( 'title' => 'Research in Action', 'url' => home_url( '/stories/' ) ),
				array( 'title' => 'Working Together',   'url' => home_url( '/partners/' ) ),
				array( 'title' => 'Publication',        'url' => home_url( '/publications/' ) ),
			),
		),
	);
}

/**
 * Render callback for the rcmi/site-header block.
 *
 * Outputs <header class="site-header"> with the logo, the "primary" nav menu
 * (desktop .nav-links with .dropdown sub-panels), CTA buttons, the mobile
 * toggle, and the #mobile-nav panel (flat .mn-group / .is-sub structure).
 * Block attributes set scoped CSS custom properties (--rcmi-header-bg, etc.)
 * on the <header> and #mobile-nav elements so the CSS fallbacks are
 * overridden only when a value is explicitly chosen.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rcmi_render_site_header_block( $attributes = array() ) {
	$home_url = home_url( '/' );

	// Build scoped CSS custom properties from block attributes.
	$style_vars = rcmi_build_header_style_vars( $attributes );

	// Build conditional classes.
	$classes = array( 'site-header' );
	if ( isset( $attributes['sticky'] ) && false === $attributes['sticky'] ) {
		$classes[] = 'not-sticky';
	}
	if ( ! empty( $attributes['transparent'] ) ) {
		$classes[] = 'is-transparent';
	}
	$header_class = esc_attr( implode( ' ', $classes ) );
	$header_style = $style_vars ? ' style="' . esc_attr( $style_vars ) . '"' : '';

	// Logo — uses an uploaded image if logoImageUrl is set, otherwise a text
	// logo (mark badge + text + subtitle) from block attributes.
	$logo_mark = isset( $attributes['logoMark'] ) ? $attributes['logoMark'] : 'RC';
	$logo_text = isset( $attributes['logoText'] ) ? $attributes['logoText'] : 'RCMI';
	$logo_sub  = isset( $attributes['logoSub'] ) ? $attributes['logoSub'] : 'Research Capacity & Mentoring Institute';
	$logo_img  = ! empty( $attributes['logoImageUrl'] ) ? $attributes['logoImageUrl'] : '';

	if ( $logo_img ) {
		$logo = '<a href="' . esc_url( $home_url ) . '" class="nav-logo nav-logo-image">'
			. '<img src="' . esc_url( $logo_img ) . '" alt="' . esc_attr( $logo_text ) . '" class="nav-logo-img" />'
			. '</a>';
	} else {
		$logo = '<a href="' . esc_url( $home_url ) . '" class="nav-logo">'
			. '<span class="mark">' . esc_html( $logo_mark ) . '</span>' . esc_html( $logo_text )
			. '<span class="sub">' . esc_html( $logo_sub ) . '</span>'
			. '</a>';
	}

	// Desktop nav (.nav-links with .dropdown sub-panels).
	$nav_links = wp_nav_menu( array(
		'theme_location' => 'primary',
		'walker'         => new RCMI_Nav_Walker(),
		'container'      => false,
		'items_wrap'     => '<ul class="nav-links">%3$s</ul>',
		'fallback_cb'    => 'rcmi_default_nav_fallback',
		'echo'           => false,
	) );

	// CTA buttons (header chrome, not part of the nav menu).
	$buttons = isset( $attributes['buttons'] ) ? $attributes['buttons'] : array(
		array( 'text' => 'Request Support',   'link' => '/#start',              'style' => 'outline' ),
		array( 'text' => 'Explore Research',  'link' => '/cores/#investigator', 'style' => 'primary' ),
	);
	$cta = '<div class="nav-cta">';
	foreach ( $buttons as $btn ) {
		if ( empty( $btn['text'] ) ) { continue; }
		$btn_style = isset( $btn['style'] ) ? $btn['style'] : 'primary';
		$btn_class = 'btn btn-' . ( 'outline' === $btn_style ? 'outline' : 'primary' );
		$btn_link  = isset( $btn['link'] ) ? $btn['link'] : '#';
		// Resolve relative links against the site URL.
		if ( 0 === strpos( $btn_link, '/' ) ) {
			$btn_link = home_url( $btn_link );
		}
		$cta .= '<a href="' . esc_url( $btn_link ) . '" class="' . esc_attr( $btn_class ) . '">' . esc_html( $btn['text'] ) . '</a>';
	}
	$cta .= '</div>';

	// Mobile toggle button.
	$toggle = '<button class="nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav">'
		. '<svg viewBox="0 0 24 24" fill="none" width="26" height="26"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
		. '</button>';

	$output  = '<header class="' . $header_class . '"' . $header_style . '>';
	$output .= '<nav class="wrap nav">' . $logo . $nav_links . $cta . $toggle . '</nav>';
	$output .= '</header>';

	// Mobile nav panel (flat .mn-group / .is-sub structure, no <ul>).
	// CSS vars are duplicated here because #mobile-nav is a sibling of <header>,
	// not a child, so custom properties set on <header> don't cascade to it.
	$mobile_links = wp_nav_menu( array(
		'theme_location' => 'primary',
		'walker'         => new RCMI_Mobile_Nav_Walker(),
		'container'      => false,
		'items_wrap'     => '%3$s',
		'fallback_cb'    => 'rcmi_default_mobile_nav_fallback',
		'echo'           => false,
	) );

	$mobile_cta  = '<div class="mn-cta">';
	foreach ( $buttons as $btn ) {
		if ( empty( $btn['text'] ) ) { continue; }
		$btn_style = isset( $btn['style'] ) ? $btn['style'] : 'primary';
		$btn_class = 'btn btn-' . ( 'outline' === $btn_style ? 'outline' : 'primary' );
		$btn_link  = isset( $btn['link'] ) ? $btn['link'] : '#';
		if ( 0 === strpos( $btn_link, '/' ) ) {
			$btn_link = home_url( $btn_link );
		}
		$mobile_cta .= '<a href="' . esc_url( $btn_link ) . '" class="' . esc_attr( $btn_class ) . '">' . esc_html( $btn['text'] ) . '</a>';
	}
	$mobile_cta .= '</div>';

	$output .= '<div id="mobile-nav" class="mobile-nav"' . $header_style . '>' . $mobile_links . $mobile_cta . '</div>';

	return $output;
}

/**
 * Build the scoped CSS custom property string for the header block from
 * its attributes. Returns a semicolon-separated string of `--name: value`
 * pairs (without the surrounding `style=""` attribute), or an empty string
 * if no color attributes are set.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function rcmi_build_header_style_vars( $attributes ) {
	$vars = array();
	$map  = array(
		'backgroundColor' => '--rcmi-header-bg',
		'textColor'       => '--rcmi-header-text',
		'accentColor'     => '--rcmi-header-accent',
		'ctaBgColor'      => '--rcmi-cta-bg',
		'ctaTextColor'    => '--rcmi-cta-text',
	);
	foreach ( $map as $attr => $var ) {
		if ( ! empty( $attributes[ $attr ] ) ) {
			$vars[] = $var . ': ' . $attributes[ $attr ];
		}
	}
	return implode( '; ', $vars );
}

/**
 * Fallback for wp_nav_menu() when no menu is assigned to "primary": renders
 * the default desktop .nav-links markup directly from rcmi_default_menu_items().
 *
 * @param array $args wp_nav_menu() arguments.
 * @return string
 */
function rcmi_default_nav_fallback( $args ) {
	$items = rcmi_default_menu_items();
	$out   = '<ul class="nav-links">';
	foreach ( $items as $item ) {
		$out .= '<li>';
		$out .= '<a href="' . esc_url( $item['url'] ) . '">';
		$out .= esc_html( $item['title'] );
		if ( ! empty( $item['children'] ) ) {
			$out .= ' <svg viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>';
		}
		$out .= '</a>';
		if ( ! empty( $item['children'] ) ) {
			$out .= '<div class="dropdown">';
			foreach ( $item['children'] as $child ) {
				$out .= '<a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['title'] ) . '</a>';
			}
			$out .= '</div>';
		}
		$out .= '</li>';
	}
	$out .= '</ul>';
	return $out;
}

/**
 * Fallback for the mobile wp_nav_menu() call: renders the default flat
 * .mn-group / .is-sub markup from rcmi_default_menu_items().
 *
 * @param array $args wp_nav_menu() arguments.
 * @return string
 */
function rcmi_default_mobile_nav_fallback( $args ) {
	$items = rcmi_default_menu_items();
	$out   = '';
	foreach ( $items as $item ) {
		if ( ! empty( $item['children'] ) ) {
			$out .= '<span class="mn-group">' . esc_html( $item['title'] ) . '</span>';
			foreach ( $item['children'] as $child ) {
				$out .= '<a class="is-sub" href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['title'] ) . '</a>';
			}
		} else {
			$out .= '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
		}
	}
	return $out;
}

/**
 * Auto-create the "Primary Menu" with the default items and assign it to the
 * "primary" location on theme activation. Runs once; skips if a menu is
 * already assigned or the menu already has items.
 */
function rcmi_auto_create_primary_menu() {
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['primary'] ) ) {
		$existing = wp_get_nav_menu_object( $locations['primary'] );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return; // A menu is already assigned to primary.
		}
	}

	$menu     = wp_get_nav_menu_object( 'Primary Menu' );
	$menu_id  = $menu ? $menu->term_id : wp_create_nav_menu( 'Primary Menu' );

	// Don't duplicate items if the menu already has some.
	$existing_items = wp_get_nav_menu_items( $menu_id );
	if ( empty( $existing_items ) ) {
		foreach ( rcmi_default_menu_items() as $item ) {
			$parent_id = wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'  => $item['title'],
				'menu-item-url'    => $item['url'],
				'menu-item-status' => 'publish',
			) );
			if ( ! empty( $item['children'] ) ) {
				foreach ( $item['children'] as $child ) {
					wp_update_nav_menu_item( $menu_id, 0, array(
						'menu-item-title'     => $child['title'],
						'menu-item-url'       => $child['url'],
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent_id,
					) );
				}
			}
		}
	}

	// Merge with existing locations so other menu assignments are preserved.
	$locations['primary'] = (int) $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}
add_action( 'after_switch_theme', 'rcmi_auto_create_primary_menu' );

// ============================================================================
// Dynamic site footer block (rcmi/site-footer)
// Renders the site footer from the "footer" WordPress nav menu (managed via
// Appearance > Menus). Each top-level menu item becomes a link column
// (.footer-col) with an <h4> heading and a <ul> of child links; the brand
// column and footer-bottom bar are rendered by the block itself (footer
// chrome, not navigation).
// ============================================================================

/**
 * Register the rcmi/site-footer dynamic block.
 */
function rcmi_register_site_footer_block() {
	register_block_type( 'rcmi/site-footer', array(
		'attributes'      => array(
			'backgroundColor' => array( 'type' => 'string', 'default' => '' ),
			'textColor'       => array( 'type' => 'string', 'default' => '' ),
			'accentColor'     => array( 'type' => 'string', 'default' => '' ),
			'borderTop'       => array( 'type' => 'boolean', 'default' => false ),
			'logoMark'        => array( 'type' => 'string', 'default' => 'RC' ),
			'logoText'        => array( 'type' => 'string', 'default' => 'RCMI' ),
			'footerText'      => array( 'type' => 'string', 'default' => 'Research Capacity & Mentoring Institute — building research capacity, developing investigators, and partnering with communities to improve chronic disease outcomes.' ),
		),
		'render_callback' => 'rcmi_render_site_footer_block',
	) );
}
add_action( 'init', 'rcmi_register_site_footer_block' );

/**
 * Default menu items used to seed the Footer Menu on theme activation and to
 * render a fallback when no menu is assigned to the "footer" location.
 *
 * Top-level items become column headings; their children become the links.
 *
 * @return array
 */
function rcmi_default_footer_menu_items() {
	return array(
		array(
			'title'    => 'About',
			'url'      => home_url( '/about/' ),
			'children' => array(
				array( 'title' => 'Mission',      'url' => home_url( '/about/#mission' ) ),
				array( 'title' => 'Why We Exist', 'url' => home_url( '/about/#why' ) ),
			),
		),
		array(
			'title'    => 'Core',
			'url'      => home_url( '/cores/' ),
			'children' => array(
				array( 'title' => 'Admin Core',                'url' => home_url( '/cores/#admin' ) ),
				array( 'title' => 'Investigator Core',         'url' => home_url( '/cores/#investigator' ) ),
				array( 'title' => 'Community Core',            'url' => home_url( '/cores/#community' ) ),
				array( 'title' => 'Research Core',             'url' => home_url( '/cores/#research' ) ),
			),
		),
		array(
			'title'    => 'Journey',
			'url'      => home_url( '/journey/' ),
			'children' => array(
				array( 'title' => 'The Investigator Journey', 'url' => home_url( '/journey/' ) ),
				array( 'title' => 'Start Collaborating',      'url' => home_url( '/#start' ) ),
			),
		),
		array(
			'title'    => 'Resource',
			'url'      => home_url( '/dashboard/' ),
			'children' => array(
				array( 'title' => 'Dashboard',          'url' => home_url( '/dashboard/' ) ),
				array( 'title' => 'Research in Action', 'url' => home_url( '/stories/' ) ),
				array( 'title' => 'Working Together',   'url' => home_url( '/partners/' ) ),
				array( 'title' => 'Publication',        'url' => home_url( '/publications/' ) ),
			),
		),
	);
}

/**
 * Render callback for the rcmi/site-footer block.
 *
 * Outputs <footer class="site-footer"> with the brand column, the "footer"
 * nav menu rendered as .footer-col columns, and the footer-bottom bar.
 *
 * @return string
 */
function rcmi_render_site_footer_block( $attributes = array() ) {
	$home_url = home_url( '/' );

	// Build scoped CSS custom properties from block attributes.
	$style_vars = array();
	$var_map    = array(
		'backgroundColor' => '--rcmi-footer-bg',
		'textColor'       => '--rcmi-footer-text',
		'accentColor'     => '--rcmi-footer-accent',
	);
	foreach ( $var_map as $attr => $var ) {
		if ( ! empty( $attributes[ $attr ] ) ) {
			$style_vars[] = $var . ': ' . $attributes[ $attr ];
		}
	}
	$footer_style = $style_vars ? ' style="' . esc_attr( implode( '; ', $style_vars ) ) . '"' : '';

	// Build conditional classes.
	$classes = array( 'site-footer' );
	if ( ! empty( $attributes['borderTop'] ) ) {
		$classes[] = 'has-border-top';
	}
	$footer_class = esc_attr( implode( ' ', $classes ) );

	// Brand column (footer chrome, not part of the nav menu).
	$logo_mark   = isset( $attributes['logoMark'] ) ? $attributes['logoMark'] : 'RC';
	$logo_text   = isset( $attributes['logoText'] ) ? $attributes['logoText'] : 'RCMI';
	$footer_desc = isset( $attributes['footerText'] ) ? $attributes['footerText'] : 'Research Capacity & Mentoring Institute — building research capacity, developing investigators, and partnering with communities to improve chronic disease outcomes.';
	$brand  = '<div class="footer-brand">';
	$brand .= '<a href="' . esc_url( $home_url ) . '" class="nav-logo"><span class="mark">' . esc_html( $logo_mark ) . '</span>' . esc_html( $logo_text ) . '</a>';
	$brand .= '<p>' . esc_html( $footer_desc ) . '</p>';
	$brand .= '</div>';

	// Footer link columns (.footer-col per top-level menu item).
	$columns = wp_nav_menu( array(
		'theme_location' => 'footer',
		'walker'         => new RCMI_Footer_Walker(),
		'container'      => false,
		'items_wrap'     => '%3$s',
		'fallback_cb'    => 'rcmi_default_footer_nav_fallback',
		'echo'           => false,
	) );

	// Footer bottom bar (static chrome).
	$bottom  = '<div class="footer-bottom">';
	$bottom .= '<span>&copy; ' . gmdate( 'Y' ) . ' UH RCMI</span>';
	$bottom .= '<span></span>';
	$bottom .= '</div>';

	$output  = '<footer class="' . $footer_class . '"' . $footer_style . '>';
	$output .= '<div class="wrap">';
	$output .= '<div class="footer-top">' . $brand . $columns . '</div>';
	$output .= $bottom;
	$output .= '</div>';
	$output .= '</footer>';

	return $output;
}

/**
 * Fallback for the footer wp_nav_menu() call when no menu is assigned to
 * "footer": renders the default .footer-col columns from
 * rcmi_default_footer_menu_items().
 *
 * @param array $args wp_nav_menu() arguments.
 * @return string
 */
function rcmi_default_footer_nav_fallback( $args ) {
	$items = rcmi_default_footer_menu_items();
	$out   = '';
	foreach ( $items as $item ) {
		$out .= '<div class="footer-col">';
		if ( ! empty( $item['children'] ) ) {
			$out .= '<h4>' . esc_html( $item['title'] ) . '</h4><ul>';
			foreach ( $item['children'] as $child ) {
				$out .= '<li><a href="' . esc_url( $child['url'] ) . '">' . esc_html( $child['title'] ) . '</a></li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<h4><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a></h4>';
		}
		$out .= '</div>';
	}
	return $out;
}

/**
 * Auto-create the "Footer Menu" with the default items and assign it to the
 * "footer" location on theme activation. Runs once; skips if a menu is
 * already assigned. Merges with existing locations so the primary menu
 * assignment is preserved.
 */
function rcmi_auto_create_footer_menu() {
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['footer'] ) ) {
		$existing = wp_get_nav_menu_object( $locations['footer'] );
		if ( $existing && ! is_wp_error( $existing ) ) {
			return; // A menu is already assigned to footer.
		}
	}

	$menu     = wp_get_nav_menu_object( 'Footer Menu' );
	$menu_id  = $menu ? $menu->term_id : wp_create_nav_menu( 'Footer Menu' );

	// Don't duplicate items if the menu already has some.
	$existing_items = wp_get_nav_menu_items( $menu_id );
	if ( empty( $existing_items ) ) {
		foreach ( rcmi_default_footer_menu_items() as $item ) {
			$parent_id = wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'  => $item['title'],
				'menu-item-url'    => $item['url'],
				'menu-item-status' => 'publish',
			) );
			if ( ! empty( $item['children'] ) ) {
				foreach ( $item['children'] as $child ) {
					wp_update_nav_menu_item( $menu_id, 0, array(
						'menu-item-title'     => $child['title'],
						'menu-item-url'       => $child['url'],
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => $parent_id,
					) );
				}
			}
		}
	}

	// Merge with existing locations so the primary assignment is preserved.
	$locations['footer'] = (int) $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}
add_action( 'after_switch_theme', 'rcmi_auto_create_footer_menu' );

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
 * Rename the extracted GitHub ZIP folder ("rcmi-theme-main") to the theme's
 * real folder name ("rcmi") BEFORE WordPress computes the install
 * destination from basename($source).
 *
 * This is the reliable fix for both problems the old post_install rename
 * caused:
 *  - WordPress core switches the active theme to $result['destination_name']
 *    after an upgrade (Theme_Upgrader::current_after). With the ZIP's folder
 *    name that became "rcmi-theme-main" — a folder our old post_install
 *    filter then deleted, breaking the active theme.
 *  - The result array is passed to upgrader_post_install by value, so
 *    changing destination_name there never reached the caller.
 *
 * The extracted folder lives in wp-content/upgrade/ and is never locked,
 * so rename() works on every platform including Windows.
 */
function rcmi_theme_fix_source_folder( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( is_wp_error( $source ) || ! isset( $hook_extra['theme'] ) ) {
		return $source;
	}
	if ( false === strpos( $hook_extra['theme'], 'rcmi' ) ) {
		return $source;
	}
	$expected = 'rcmi';
	if ( basename( $source ) === $expected ) {
		return $source;
	}
	$new_source = trailingslashit( dirname( untrailingslashit( $source ) ) ) . $expected;
	if ( @rename( untrailingslashit( $source ), $new_source ) ) {
		return trailingslashit( $new_source );
	}
	return $source;
}
add_filter( 'upgrader_source_selection', 'rcmi_theme_fix_source_folder', 10, 4 );

/**
 * Prevent WordPress from aborting the update when the old theme folder
 * cannot be deleted. On Windows, the active theme's files are locked by
 * PHP (opcache) and delete fails, which would otherwise abort the install.
 *
 * By returning true here, the install proceeds: WordPress's copy step
 * (copy_dir with overwrite=true) overwrites the old files in place.
 */
function rcmi_theme_skip_clear_destination( $removed, $local_destination, $remote_destination, $hook_extra ) {
	if ( ! isset( $hook_extra['theme'] ) ) {
		return $removed;
	}
	if ( false === strpos( $hook_extra['theme'], 'rcmi' ) ) {
		return $removed;
	}
	// Override WordPress's delete_old_theme (which fails on Windows
	// because locked files can't be deleted). Return true so the install
	// proceeds — the copy step overwrites files in place (copy_dir uses
	// overwrite=true).
	return true;
}
add_filter( 'upgrader_clear_destination', 'rcmi_theme_skip_clear_destination', 20, 4 );

/**
 * Post-install bookkeeping: records the installed commit SHA and clears
 * cached update data. All file placement is handled by WordPress itself
 * because rcmi_theme_fix_source_folder() renames the extracted ZIP folder
 * before the destination path is computed.
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

	// File placement is already correct (see rcmi_theme_fix_source_folder).
	// Just clear the theme cache so WordPress sees the new files.
	search_theme_directories( true );

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
 * Show an admin notice on the Themes page when a theme update is available,
 * with a prominent "Check for updates" button.
 */
function rcmi_theme_update_admin_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'themes' !== $screen->id ) {
		return;
	}

	// Always show a "Check for updates" button on the Themes page.
	$check_url = add_query_arg( 'rcmi_theme_check_updates', '1', admin_url( 'themes.php' ) );

	// Check if an update is available.
	$commit = rcmi_theme_get_github_commit();
	$installed_sha = rcmi_theme_get_installed_sha();
	$update_available = $commit && $commit['sha'] !== $installed_sha;

	if ( $update_available ) {
		?>
		<div class="notice notice-warning is-dismissible" style="display:flex;align-items:center;gap:12px;">
			<p style="margin:0;"><strong>RCMI Theme:</strong> A new update is available
			(<?php echo esc_html( $commit['short_sha'] ); ?> — <?php echo esc_html( wp_trim_words( $commit['message'], 8 ) ); ?>).</p>
			<a href="<?php echo esc_url( $check_url ); ?>" class="button button-primary">Check for updates</a>
		</div>
		<?php
	} else {
		// Show a subtle "Check for updates" link even when up to date.
		?>
		<div class="notice notice-info is-dismissible" style="display:flex;align-items:center;gap:12px;">
			<p style="margin:0;">RCMI Theme: Check for updates from GitHub.</p>
			<a href="<?php echo esc_url( $check_url ); ?>" class="button button-secondary">Check for updates</a>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'rcmi_theme_update_admin_notice' );

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
