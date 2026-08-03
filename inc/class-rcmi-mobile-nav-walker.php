<?php
/**
 * Custom nav walker for the RCMI mobile navigation panel.
 *
 * Produces a FLAT sequence of elements (no <ul>) inside #mobile-nav, matching
 * the existing .mobile-nav CSS:
 *
 *   <a href="...">Home</a>
 *   <span class="mn-group">About</span>
 *   <a class="is-sub" href="...">Mission</a>
 *   <a class="is-sub" href="...">Why We Exist</a>
 *   <a href="...">The Journey</a>
 *   <span class="mn-group">Resource</span>
 *   ...
 *
 * Top-level items WITH children render as a non-linked <span class="mn-group">
 * label followed by their children as <a class="is-sub"> links. Top-level
 * items WITHOUT children render as a plain <a> link. The hierarchy is
 * flattened — start_lvl/end_lvl are no-ops.
 *
 * Use with wp_nav_menu( array( 'items_wrap' => '%3$s', ... ) ) so no wrapping
 * <ul> is emitted.
 *
 * @package rcmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker_Nav_Menu subclass for the mobile #mobile-nav panel.
 */
class RCMI_Mobile_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * No sub-level wrapper in the mobile panel (hierarchy is flattened).
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// Intentionally empty: mobile nav is flat.
	}

	/**
	 * No sub-level wrapper in the mobile panel.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		// Intentionally empty: mobile nav is flat.
	}

	/**
	 * Renders each menu item as a self-contained element.
	 *
	 * Depth 0 with children   -> <span class="mn-group">Title</span>
	 * Depth 0 without children -> <a href="...">Title</a>
	 * Depth >= 1              -> <a class="is-sub" href="...">Title</a>
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int      $id      Optional. ID of the current menu item. Default 0.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$is_active = in_array( 'current-menu-item', $classes, true ) || in_array( 'current_page_item', $classes, true );
		$href      = ! empty( $item->url ) ? $item->url : '';

		if ( 0 === $depth ) {
			if ( $this->has_children ) {
				// Parent becomes a non-linked group label.
				$output .= '<span class="mn-group">' . esc_html( $item->title ) . '</span>';
			} else {
				$class = $is_active ? ' class="is-active"' : '';
				$output .= '<a' . $class . ' href="' . esc_url( $href ) . '">' . esc_html( $item->title ) . '</a>';
			}
		} else {
			// Sub-item: indented link inside the mobile panel.
			$class = $is_active ? ' class="is-sub is-active"' : ' class="is-sub"';
			$output .= '<a' . $class . ' href="' . esc_url( $href ) . '">' . esc_html( $item->title ) . '</a>';
		}
	}

	/**
	 * No closing tag needed — start_el() emits complete elements.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int      $id      Optional. ID of the current menu item. Default 0.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		// Intentionally empty: each element is self-closing in start_el().
	}
}
