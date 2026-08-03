<?php
/**
 * Custom nav walker for the RCMI footer link columns.
 *
 * Produces one <div class="footer-col"> per top-level menu item, matching the
 * existing footer CSS:
 *
 *   <div class="footer-col">
 *     <h4>About</h4>
 *     <ul>
 *       <li><a href="...">Mission</a></li>
 *       <li><a href="...">Why We Exist</a></li>
 *     </ul>
 *   </div>
 *
 * Top-level items WITH children   -> <div class="footer-col"><h4>Title</h4><ul>...</ul></div>
 * Top-level items WITHOUT children -> <div class="footer-col"><h4><a href="...">Title</a></h4></div>
 *                                     (a linked heading, no <ul> — e.g. a "Journey" column with
 *                                      a single entry, or a standalone link column)
 *
 * Use with wp_nav_menu( array( 'items_wrap' => '%3$s', ... ) ) so no wrapping
 * <ul> is emitted around the columns.
 *
 * @package rcmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker_Nav_Menu subclass for the footer link columns.
 */
class RCMI_Footer_Walker extends Walker_Nav_Menu {

	/**
	 * Whether the current top-level item has children (set in start_el).
	 *
	 * @var bool
	 */
	private $current_has_children = false;

	/**
	 * No sub-level wrapper — children are emitted inside the <ul> opened by
	 * the parent's start_el.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		// Intentionally empty: the <ul> is opened in start_el() at depth 0.
	}

	/**
	 * No sub-level wrapper.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		// Intentionally empty: the <ul> is closed in end_el() at depth 0.
	}

	/**
	 * Renders each menu item.
	 *
	 * Depth 0 with children    -> opens <div class="footer-col"><h4>Title</h4><ul>
	 * Depth 0 without children -> <div class="footer-col"><h4><a href="...">Title</a></h4></div>
	 * Depth >= 1              -> <li><a href="...">Title</a></li>
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
		$title     = esc_html( $item->title );

		if ( 0 === $depth ) {
			$this->current_has_children = $this->has_children;
			$output .= '<div class="footer-col">';
			if ( $this->has_children ) {
				// Column heading (non-linked) + open the link list.
				$output .= '<h4>' . $title . '</h4><ul>';
			} else {
				// Standalone link column: linked heading, no <ul>.
				$output .= '<h4><a href="' . esc_url( $href ) . '">' . $title . '</a></h4>';
			}
		} else {
			// Sub-item: <li><a>...</a></li>
			$class = $is_active ? ' class="is-active"' : '';
			$output .= '<li><a' . $class . ' href="' . esc_url( $href ) . '">' . $title . '</a></li>';
		}
	}

	/**
	 * Closes the column markup opened in start_el().
	 *
	 * Depth 0 with children    -> closes </ul></div>
	 * Depth 0 without children -> closes </div> (heading was self-contained)
	 * Depth >= 1              -> nothing (the <li> was closed in start_el)
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int      $id      Optional. ID of the current menu item. Default 0.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		if ( 0 === $depth ) {
			if ( $this->current_has_children ) {
				$output .= '</ul>';
			}
			$output .= '</div>';
		}
	}
}
