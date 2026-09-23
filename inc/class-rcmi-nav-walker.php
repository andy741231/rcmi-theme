<?php
/**
 * Custom nav walker for the RCMI desktop header.
 *
 * Produces markup that matches the existing .nav-links / .dropdown CSS:
 *
 *   <ul class="nav-links">
 *     <li><a href="...">Home</a></li>
 *     <li><a href="...">About <svg.../></a>
 *       <div class="dropdown">
 *         <a href="...">Mission</a>
 *         <a href="...">Why We Exist</a>
 *         <div class="dd-item"><a href="...">More <svg.../></a>
 *           <div class="dropdown dd-sub"><a href="...">Third level</a></div>
 *         </div>
 *       </div>
 *     </li>
 *     ...
 *   </ul>
 *
 * Sub-items are rendered as bare <a> tags inside a .dropdown div (no <ul>/<li>),
 * which is what the theme CSS expects. Sub-items with their own children wrap
 * the anchor in <div class="dd-item"> and open a nested <div class="dropdown
 * dd-sub"> flyout panel, so menu depth is unlimited (third level and deeper
 * fly out to the side).
 *
 * @package rcmi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker_Nav_Menu subclass for the desktop .nav-links list.
 */
class RCMI_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Starts the list before the elements are added.
	 *
	 * For depth 0 (top level) we open the <ul class="nav-links">. For any
	 * deeper level we instead open a <div class="dropdown"> — sub-items are
	 * rendered as bare <a> tags (no <li>), matching the CSS. Depth 2+ opens
	 * a nested flyout panel (.dd-sub) inside the wrapping .dd-item.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$output .= 0 === $depth ? '<div class="dropdown">' : '<div class="dropdown dd-sub">';
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</div>';
	}

	/**
	 * Starts the element output.
	 *
	 * Top-level items (depth 0) are wrapped in <li> and render an <a> with an
	 * optional chevron SVG when the item has children. Sub-items (depth >= 1)
	 * render as bare <a> tags inside the .dropdown div — no <li> wrapper.
	 * Sub-items with children are wrapped in <div class="dd-item"> (the
	 * positioning context for the nested flyout) and get a right-pointing
	 * chevron.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int      $id      Optional. ID of the current menu item. Default 0.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$is_active = in_array( 'current-menu-item', $classes, true ) || in_array( 'current_page_item', $classes, true );

		// Build the anchor attributes.
		$atts = array(
			'href'  => ! empty( $item->url ) ? $item->url : '',
			'class' => $is_active ? 'is-active' : '',
		);

		if ( 0 === $depth ) {
			// Top-level: <li><a ...>Label [chevron]</a>
			$output .= '<li>';

			$has_children = $this->has_children;
			$atts['class'] = trim( $atts['class'] );
			$attr_string  = '';
			foreach ( $atts as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$attr_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}

			$output .= '<a' . $attr_string . '>';
			$output .= esc_html( $item->title );

			if ( $has_children ) {
				$output .= ' <svg viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5"/></svg>';
			}

			$output .= '</a>';
		} else {
			// Sub-item: bare <a> inside .dropdown, or wrapped in .dd-item when
			// it has its own children (nested flyout panel follows).
			$attr_string = '';
			foreach ( $atts as $key => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$attr_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			if ( $this->has_children ) {
				$output .= '<div class="dd-item"><a' . $attr_string . '>' . esc_html( $item->title );
				$output .= ' <svg viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.5"/></svg></a>';
			} else {
				$output .= '<a' . $attr_string . '>' . esc_html( $item->title ) . '</a>';
			}
		}
	}

	/**
	 * Ends the element output.
	 *
	 * Only top-level items get a closing </li>; sub-items are bare anchors,
	 * except sub-items with children whose .dd-item wrapper needs </div>.
	 * (end_el runs after children are walked, so $this->has_children already
	 * reflects the last descendant — the core-added menu-item-has-children
	 * class on the item itself is the reliable check.)
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
	 * @param int      $id      Optional. ID of the current menu item. Default 0.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		if ( 0 === $depth ) {
			$output .= '</li>';
		} elseif ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$output .= '</div>';
		}
	}
}
