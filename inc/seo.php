<?php
/**
 * SEO essentials for the RCMI theme: document titles, meta descriptions,
 * Open Graph / Twitter cards, JSON-LD schema, and a robots.txt fallback.
 *
 * Implemented in the theme (rather than a plugin) so every change ships
 * automatically through the GitHub auto-updater.
 *
 * @package rcmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rcmi_seo_default_settings() {
	return array(
		'site_name'                   => 'RCMI at UH',
		'home_title'                  => 'RCMI at University of Houston',
		'home_tagline'                => 'Chronic Disease Health Disparities Research',
		'default_description'         => 'The Research Centers in Minority Institutions (RCMI) at the University of Houston advances chronic disease health disparities research — pilot funding, mentorship, biostatistics, and core services for UH investigators and communities.',
		'social_image_url'            => '',
		'organization_name'           => 'RCMI at UH',
		'organization_alternate_name' => 'Research Centers in Minority Institutions at the University of Houston',
		'parent_organization_name'    => 'University of Houston',
		'parent_organization_url'     => 'https://www.uh.edu/',
	);
}

function rcmi_seo_get_settings() {
	$saved = get_option( 'rcmi_seo_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, rcmi_seo_default_settings() );
}

function rcmi_seo_sanitize_settings( $input ) {
	$defaults = rcmi_seo_default_settings();
	if ( ! is_array( $input ) ) {
		$input = array();
	}

	$output = array();
	foreach ( array( 'site_name', 'home_title', 'home_tagline', 'organization_name', 'organization_alternate_name', 'parent_organization_name' ) as $field ) {
		$output[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
	}
	$output['default_description'] = isset( $input['default_description'] ) ? sanitize_textarea_field( $input['default_description'] ) : '';
	foreach ( array( 'social_image_url', 'parent_organization_url' ) as $field ) {
		$output[ $field ] = isset( $input[ $field ] ) ? esc_url_raw( $input[ $field ] ) : '';
	}

	foreach ( array( 'site_name', 'home_title', 'default_description', 'organization_name' ) as $field ) {
		if ( '' === $output[ $field ] ) {
			$output[ $field ] = $defaults[ $field ];
		}
	}

	return $output;
}

/**
 * Human-readable site name used across SEO tags.
 *
 * @return string
 */
function rcmi_seo_site_name() {
	$settings = rcmi_seo_get_settings();
	return $settings['site_name'];
}

/**
 * Fallback meta description when a page has no custom one.
 *
 * @return string
 */
function rcmi_seo_default_description() {
	$settings = rcmi_seo_get_settings();
	return $settings['default_description'];
}

function rcmi_seo_social_image() {
	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	}
	if ( ! $image ) {
		$settings = rcmi_seo_get_settings();
		$image    = $settings['social_image_url'];
	}
	if ( ! $image ) {
		$image = get_template_directory_uri() . '/assets/images/og-image.jpg';
	}
	return $image;
}

// ============================================================================
// Document title
// Front page:  "RCMI at University of Houston – Chronic Disease Health
//               Disparities Research"
// Inner pages: "Page Title – RCMI at UH"
// ============================================================================
function rcmi_seo_document_title_parts( $parts ) {
	$settings = rcmi_seo_get_settings();
	if ( is_front_page() ) {
		$parts['title']   = $settings['home_title'];
		$parts['tagline'] = $settings['home_tagline'];
		unset( $parts['site'] );
	} else {
		if ( is_singular() ) {
			$custom = get_post_meta( get_the_ID(), 'rcmi_seo_title', true );
			if ( $custom ) {
				$parts['title'] = $custom;
			}
		}
		if ( isset( $parts['site'] ) ) {
			$parts['site'] = $settings['site_name'];
		}
	}
	return $parts;
}
add_filter( 'document_title_parts', 'rcmi_seo_document_title_parts' );

// ============================================================================
// Meta description
// ============================================================================

/**
 * Resolve the meta description for the current request.
 * Order: custom per-post "Search description" field > excerpt > trimmed
 * content > site default.
 *
 * @return string
 */
function rcmi_seo_description() {
	if ( is_singular() ) {
		$post = get_post();
		if ( $post ) {
			$custom = get_post_meta( $post->ID, 'rcmi_seo_description', true );
			if ( $custom ) {
				return $custom;
			}
			// Front page content is hero/section chrome, not prose — the
			// curated site description makes a better search snippet.
			if ( is_front_page() ) {
				return rcmi_seo_default_description();
			}
			if ( has_excerpt( $post ) ) {
				return wp_strip_all_tags( get_the_excerpt( $post ), true );
			}
			$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ), true );
			$text = trim( preg_replace( '/\s+/', ' ', $text ) );
			if ( $text ) {
				return wp_trim_words( $text, 30, '' );
			}
		}
	}
	return rcmi_seo_default_description();
}

/**
 * Register the per-post/page search title and description meta fields.
 */
function rcmi_register_seo_meta() {
	foreach ( array( 'post', 'page' ) as $post_type ) {
		foreach ( array( 'rcmi_seo_title', 'rcmi_seo_description' ) as $key ) {
			register_post_meta( $post_type, $key, array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			) );
		}
	}
}
add_action( 'init', 'rcmi_register_seo_meta' );

/**
 * Add the "Search Appearance (SEO)" meta box to posts and pages.
 */
function rcmi_seo_meta_box() {
	foreach ( array( 'post', 'page' ) as $post_type ) {
		add_meta_box(
			'rcmi_seo',
			__( 'Search Appearance (SEO)', 'rcmi' ),
			'rcmi_seo_meta_box_html',
			$post_type,
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'rcmi_seo_meta_box' );

function rcmi_seo_meta_box_html( $post ) {
	wp_nonce_field( 'rcmi_seo_nonce', 'rcmi_seo_nonce_field' );
	$title = get_post_meta( $post->ID, 'rcmi_seo_title', true );
	$desc  = get_post_meta( $post->ID, 'rcmi_seo_description', true );
	?>
	<label for="rcmi_seo_title"><strong><?php esc_html_e( 'Search title', 'rcmi' ); ?></strong></label>
	<p class="description" style="margin:4px 0 8px;">
		<?php esc_html_e( 'Title shown in Google results. Leave empty to use this page’s own title.', 'rcmi' ); ?>
	</p>
	<input type="text" id="rcmi_seo_title" name="rcmi_seo_title" value="<?php echo esc_attr( $title ); ?>"
		maxlength="200" style="width:100%;margin-bottom:12px;" />
	<label for="rcmi_seo_description"><strong><?php esc_html_e( 'Search description', 'rcmi' ); ?></strong></label>
	<p class="description" style="margin:4px 0 8px;">
		<?php esc_html_e( 'Text shown under this page’s title in Google results. Aim for about 150–160 characters. Leave empty to auto-generate from the excerpt or page content.', 'rcmi' ); ?>
	</p>
	<textarea id="rcmi_seo_description" name="rcmi_seo_description" rows="3" maxlength="300" style="width:100%;"
		placeholder="<?php echo esc_attr( rcmi_seo_default_description() ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
	<?php
}

function rcmi_save_seo_meta( $post_id ) {
	if ( ! isset( $_POST['rcmi_seo_nonce_field'] ) || ! wp_verify_nonce( $_POST['rcmi_seo_nonce_field'], 'rcmi_seo_nonce' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$title = isset( $_POST['rcmi_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['rcmi_seo_title'] ) ) : '';
	update_post_meta( $post_id, 'rcmi_seo_title', $title );
	$desc = isset( $_POST['rcmi_seo_description'] ) ? sanitize_text_field( wp_unslash( $_POST['rcmi_seo_description'] ) ) : '';
	update_post_meta( $post_id, 'rcmi_seo_description', $desc );
}
add_action( 'save_post', 'rcmi_save_seo_meta' );

// ============================================================================
// wp_head output: meta description, Open Graph, Twitter cards, JSON-LD
// ============================================================================
function rcmi_seo_head() {
	if ( is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$description = rcmi_seo_description();
	$title       = wp_get_document_title();
	global $wp;
	$url = wp_get_canonical_url();
	if ( ! $url ) {
		$url = home_url( isset( $wp->request ) && $wp->request ? trailingslashit( $wp->request ) : '/' );
	}

	$image = rcmi_seo_social_image();

	echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";

	// Open Graph.
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) . '" />' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta property="og:url" content="' . esc_attr( $url ) . '" />' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( rcmi_seo_site_name() ) . '" />' . "\n";
	echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";

	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '" />' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '" />' . "\n";
	}

	// Twitter cards.
	echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '" />' . "\n";
	echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";

	// Structured data (front page only): identifies the program to Google so
	// "RCMI" is associated with the University of Houston, not the acronym alone.
	if ( is_front_page() ) {
		$settings = rcmi_seo_get_settings();
		$org      = array(
			'@type'       => 'ResearchOrganization',
			'@id'         => home_url( '/#org' ),
			'name'        => $settings['organization_name'],
			'url'         => home_url( '/' ),
			'description' => rcmi_seo_default_description(),
			'image'       => $image,
		);
		if ( '' !== $settings['organization_alternate_name'] ) {
			$org['alternateName'] = $settings['organization_alternate_name'];
		}
		$logo = get_site_icon_url( 512 );
		if ( $logo ) {
			$org['logo'] = $logo;
		}
		if ( '' !== $settings['parent_organization_name'] ) {
			$parent = array(
				'@type' => 'CollegeOrUniversity',
				'name'  => $settings['parent_organization_name'],
			);
			if ( '' !== $settings['parent_organization_url'] ) {
				$parent['url'] = $settings['parent_organization_url'];
			}
			$org['parentOrganization'] = $parent;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'     => 'WebSite',
					'@id'       => home_url( '/#website' ),
					'url'       => home_url( '/' ),
					'name'      => rcmi_seo_site_name(),
					'publisher' => array( '@id' => home_url( '/#org' ) ),
				),
				$org,
			),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'rcmi_seo_head', 5 );

function rcmi_seo_admin_menu() {
	// Group under the plugin's top-level RCMI Toolkit menu when it is
	// registered (rcmi-toolkit adds it at admin_menu priority 5); fall back
	// to Settings so this page still works if the plugin is deactivated.
	if ( isset( $GLOBALS['admin_page_hooks']['rcmi-toolkit'] ) ) {
		add_submenu_page(
			'rcmi-toolkit',
			__( 'RCMI SEO', 'rcmi' ),
			__( 'SEO', 'rcmi' ),
			'manage_options',
			'rcmi-seo',
			'rcmi_seo_settings_page'
		);
	} else {
		add_options_page(
			__( 'RCMI SEO', 'rcmi' ),
			__( 'RCMI SEO', 'rcmi' ),
			'manage_options',
			'rcmi-seo',
			'rcmi_seo_settings_page'
		);
	}
}
// Priority 20: must run after rcmi-toolkit registers its menu at priority 5.
add_action( 'admin_menu', 'rcmi_seo_admin_menu', 20 );

/**
 * Redirect the old "Settings → RCMI SEO" URL to the toolkit menu. Runs on
 * admin_page_access_denied because the options-general.php URL 403s before
 * admin_init fires.
 */
function rcmi_seo_redirect_legacy_url() {
	global $pagenow;
	if ( 'options-general.php' !== $pagenow || ! isset( $_GET['page'] ) || 'rcmi-seo' !== $_GET['page'] ) {
		return;
	}
	if ( ! isset( $GLOBALS['admin_page_hooks']['rcmi-toolkit'] ) ) {
		return; // SEO page still lives under Settings; let the 403 stand.
	}
	wp_safe_redirect( admin_url( 'admin.php?page=rcmi-seo' ) );
	exit;
}
add_action( 'admin_page_access_denied', 'rcmi_seo_redirect_legacy_url' );

function rcmi_seo_register_settings() {
	register_setting( 'rcmi_seo', 'rcmi_seo_settings', array(
		'type'              => 'array',
		'sanitize_callback' => 'rcmi_seo_sanitize_settings',
		'default'           => rcmi_seo_default_settings(),
	) );
}
add_action( 'admin_init', 'rcmi_seo_register_settings' );

function rcmi_seo_admin_assets( $hook ) {
	global $pagenow;
	// Match on the page query arg — the page may sit under the RCMI menu
	// (admin.php) or Settings (options-general.php), and hook suffixes are
	// derived from menu titles.
	if ( ! in_array( $pagenow, array( 'admin.php', 'options-general.php' ), true )
		|| ! isset( $_GET['page'] ) || 'rcmi-seo' !== $_GET['page'] ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'rcmi_seo_admin_assets' );

function rcmi_seo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings       = rcmi_seo_get_settings();
	$fallback_image = get_template_directory_uri() . '/assets/images/og-image.jpg';
	$preview_image  = $settings['social_image_url'] ? $settings['social_image_url'] : $fallback_image;
	$verify_url     = home_url( '/google5d32ce9c36aa7852.html' );
	$sitemap_url    = home_url( '/wp-sitemap.xml' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php settings_errors(); ?>
		<?php if ( ! defined( 'RCMI_TOOLKIT_VERSION' ) ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'The RCMI Toolkit plugin is not active. SEO settings still work here, but the RCMI admin menu, custom blocks, and analytics need the rcmi-toolkit plugin installed and activated.', 'rcmi' ); ?></p></div>
		<?php endif; ?>
		<p>
			<?php esc_html_e( 'These settings control site-wide search and social metadata. Titles and descriptions for individual posts and pages are edited in the Search Appearance (SEO) box on each post or page.', 'rcmi' ); ?>
		</p>
		<form action="options.php" method="post">
			<?php settings_fields( 'rcmi_seo' ); ?>

			<h2><?php esc_html_e( 'Search appearance', 'rcmi' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="rcmi_seo_home_title"><?php esc_html_e( 'Homepage title', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[home_title]" type="text" id="rcmi_seo_home_title" value="<?php echo esc_attr( $settings['home_title'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'Main part of the homepage document title in search results.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_home_tagline"><?php esc_html_e( 'Homepage title tagline', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[home_tagline]" type="text" id="rcmi_seo_home_tagline" value="<?php echo esc_attr( $settings['home_tagline'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Appended to the homepage title after a dash. May be left blank.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_site_name"><?php esc_html_e( 'Inner-page title suffix', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[site_name]" type="text" id="rcmi_seo_site_name" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'Site name appended to inner-page titles ("Page – RCMI at UH") and used as og:site_name.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_default_description"><?php esc_html_e( 'Default meta description', 'rcmi' ); ?></label></th>
						<td>
							<textarea name="rcmi_seo_settings[default_description]" id="rcmi_seo_default_description" rows="3" maxlength="300" class="large-text" required><?php echo esc_textarea( $settings['default_description'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Used when a page has no custom Search description.', 'rcmi' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Social sharing', 'rcmi' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="rcmi_seo_social_image_url"><?php esc_html_e( 'Default social image URL', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[social_image_url]" type="url" id="rcmi_seo_social_image_url" value="<?php echo esc_attr( $settings['social_image_url'] ); ?>" class="regular-text" />
							<button type="button" class="button" id="rcmi_seo_social_image_select"><?php esc_html_e( 'Select image', 'rcmi' ); ?></button>
							<button type="button" class="button" id="rcmi_seo_social_image_remove"><?php esc_html_e( 'Remove image', 'rcmi' ); ?></button>
							<p class="description">
								<?php esc_html_e( 'Used for og:image and twitter:image. A post or page featured image overrides it. When blank, the theme’s assets/images/og-image.jpg is used. Recommended size: 1200 × 630 pixels.', 'rcmi' ); ?>
							</p>
							<p><img id="rcmi_seo_social_image_preview" src="<?php echo esc_url( $preview_image ); ?>" alt="" style="max-width:300px;height:auto;border:1px solid #ddd;" /></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Organization schema', 'rcmi' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="rcmi_seo_organization_name"><?php esc_html_e( 'Organization name', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[organization_name]" type="text" id="rcmi_seo_organization_name" value="<?php echo esc_attr( $settings['organization_name'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'Name used in the front-page ResearchOrganization structured data.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_organization_alternate_name"><?php esc_html_e( 'Organization alternate name', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[organization_alternate_name]" type="text" id="rcmi_seo_organization_alternate_name" value="<?php echo esc_attr( $settings['organization_alternate_name'] ); ?>" class="large-text" />
							<p class="description"><?php esc_html_e( 'Full or alternate name. May be left blank.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_parent_organization_name"><?php esc_html_e( 'Parent organization name', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[parent_organization_name]" type="text" id="rcmi_seo_parent_organization_name" value="<?php echo esc_attr( $settings['parent_organization_name'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'May be left blank to omit the parent organization.', 'rcmi' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rcmi_seo_parent_organization_url"><?php esc_html_e( 'Parent organization URL', 'rcmi' ); ?></label></th>
						<td>
							<input name="rcmi_seo_settings[parent_organization_url]" type="url" id="rcmi_seo_parent_organization_url" value="<?php echo esc_attr( $settings['parent_organization_url'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'May be left blank to omit the parent organization URL.', 'rcmi' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Google Search Console', 'rcmi' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Verification URL', 'rcmi' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $verify_url ); ?>"><?php echo esc_html( $verify_url ); ?></a>
							<p class="description">
								<?php esc_html_e( 'The HTML verification file ships with the theme; this URL starts serving it after the next deployment. Use this address for the HTML file verification method in Google Search Console.', 'rcmi' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sitemap', 'rcmi' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $sitemap_url ); ?>"><?php echo esc_html( $sitemap_url ); ?></a>
							<p class="description"><?php esc_html_e( 'Submit this sitemap in Search Console after verification succeeds.', 'rcmi' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<script>
	jQuery(function($){
		var frame;
		var fallback = <?php echo wp_json_encode( $fallback_image ); ?>;
		$('#rcmi_seo_social_image_select').on('click', function(e){
			e.preventDefault();
			if (!frame) {
				frame = wp.media({
					title: <?php echo wp_json_encode( __( 'Select default social image', 'rcmi' ) ); ?>,
					button: { text: <?php echo wp_json_encode( __( 'Use this image', 'rcmi' ) ); ?> },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function(){
					var attachment = frame.state().get('selection').first().toJSON();
					var url = attachment.url;
					$('#rcmi_seo_social_image_url').val(url).trigger('change');
				});
			}
			frame.open();
		});
		$('#rcmi_seo_social_image_remove').on('click', function(e){
			e.preventDefault();
			$('#rcmi_seo_social_image_url').val('').trigger('change');
		});
		$('#rcmi_seo_social_image_url').on('input change', function(){
			$('#rcmi_seo_social_image_preview').attr('src', $(this).val() || fallback);
		});
	});
	</script>
	<?php
}

function rcmi_seo_google_verification() {
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
	if ( ! $path || 'google5d32ce9c36aa7852.html' !== basename( rawurldecode( $path ) ) ) {
		return;
	}
	$file = get_template_directory() . '/google5d32ce9c36aa7852.html';
	if ( ! is_readable( $file ) ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	readfile( $file );
	exit;
}
add_action( 'template_redirect', 'rcmi_seo_google_verification', 0 );

// ============================================================================
// robots.txt fallback
// On the production IIS host, /rcmi/robots.txt reaches WordPress but renders
// the themed 404 template instead of WP's virtual robots.txt (is_robots() is
// never set). When that happens, serve a real robots.txt here so crawlers get
// a clean allow-all plus the sitemap location.
// ============================================================================
function rcmi_seo_robots_txt() {
	if ( ! is_404() ) {
		return;
	}
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH );
	if ( ! $path || ! preg_match( '~/robots\.txt$~i', $path ) ) {
		return;
	}

	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );

	if ( '0' === get_option( 'blog_public' ) ) {
		echo "User-agent: *\nDisallow: /\n";
	} else {
		$admin_path = wp_parse_url( admin_url(), PHP_URL_PATH );
		echo "User-agent: *\n";
		echo 'Disallow: ' . esc_html( $admin_path ) . "\n";
		echo 'Allow: ' . esc_html( $admin_path ) . "admin-ajax.php\n\n";
		echo 'Sitemap: ' . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
	}
	exit;
}
add_action( 'template_redirect', 'rcmi_seo_robots_txt', 0 );
