<?php
/**
 * @package Expandable_Dashboard_Recent_Comments
 * @author Scott Reilly
 * @version 2.2
 */
/*
Plugin Name: Expandable Dashboard Recent Comments
Version: 2.2
Plugin URI: http://coffee2code.com/wp-plugins/expandable-dashboard-recent-comments/
Author: Scott Reilly
Author URI: http://coffee2code.com/
Text Domain: expandable-dashboard-recent-comments
Domain Path: /lang/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Description: Adds links for in-place expansion of comment excerpts on the admin dashboard 'Recent Comments' widget to view full comments.

Compatible with WordPress 3.1+ through 3.6.

=>> Read the accompanying readme.txt file for instructions and documentation.
=>> Also, visit the plugin's homepage for additional information and updates.
=>> Or visit: http://wordpress.org/extend/plugins/expandable-dashboard-recent-comments/
*/

/*
	Copyright (c) 2009-2013 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( is_admin() && ! class_exists( 'c2c_ExpandableDashboardRecentComments' ) ) :

class c2c_ExpandableDashboardRecentComments {
	private static $_start_expanded       = null;
	private static $_has_output_all_links = false;

	/**
	 * Returns version of the plugin.
	 *
	 * @since 2.0
	 */
	public static function version() {
		return '2.2';
	}

	/**
	 * Initialization.
	 */
	public static function init() {
		add_action( 'load-index.php', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Loads text domain and registers actions/filters.
	 */
	public static function do_init() {
		// Load textdomain
		load_plugin_textdomain( 'c2c_edrc', false, basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'lang' );

		// Hook the comment excerpt to do our magic
		add_filter( 'comment_excerpt',            array( __CLASS__, 'expandable_comment_excerpts' ) );
		// Add action link to comment row
		add_filter( 'comment_row_actions',        array( __CLASS__, 'comment_row_action' ), 10, 2 );
		// Enqueues JS for admin page
		add_action( 'admin_enqueue_scripts',      array( __CLASS__, 'enqueue_admin_js' ) );
		// Register and enqueue styles for admin page
		self::register_styles();
		add_action( 'admin_enqueue_scripts',      array( __CLASS__, 'enqueue_admin_css' ) );
	}

	/**
	 * Registers styles.
	 *
	 * @since 2.0
	 */
	public static function register_styles() {
		wp_register_style( __CLASS__, plugins_url( 'assets/admin.css', __FILE__ ) );
	}

	/**
	 * Enqueues stylesheets.
	 *
	 * @since 2.0
	 */
	public static function enqueue_admin_css() {
		wp_enqueue_style( __CLASS__ );
	}

	/**
	 * Enqueues JS.
	 *
	 * @since 2.0
	 */
	public static function enqueue_admin_js() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( __CLASS__, plugins_url( 'assets/admin.js', __FILE__ ), array( 'jquery' ), self::version(), true );
	}

	/**
	 * Indicates if the given comment should be initially shown expanded.
	 *
	 * @since 2.0
	 *
	 * @param object $comment The comment being displayed
	 * @return boolean
	 */
	private static function is_comment_initially_expanded( $comment ) {
		if ( null === self::$_start_expanded )
			self::$_start_expanded = apply_filters( 'c2c_expandable_dashboard_recent_comments_start_expanded', false, $comment );
		return self::$_start_expanded;
	}

	/**
	 * Determines if text has been truncated as an excerpt.
	 *
	 * '...' used pre-WP3.6, '&hellip;' thereafter
	 *
	 * Only necessary while maintaining pre-WP3.6 support.
	 *
	 * @since 2.2
	 *
	 * @param string $text The text
	 * @return boolean
	 */
	private static function is_text_excerpted( $text ) {
		if ( substr( $text, -8 ) == '&hellip;' || substr( $text, -3 ) == '...' )
			return true;
		else
			return false;
	}

	/**
	 * Returns the ellipsis used to denote text truncated for an excerpt.
	 *
	 * In pre-WP3.6, '...' was used. Afterwards, '&hellip;' was used.
	 *
	 * Only necessary while maintaining pre-WP3.6 support.
	 *
	 * @since 2.2
	 *
	 * @param string $text The excerpt
	 * @return string
	 */
	private static function get_ellipsis( $text ) {
		$hellip = '';
		if ( self::is_text_excerpted( $text ) ) {
			if ( substr( $text, -3 ) == '...' )
				$hellip = '...';
			else
				$hellip = '&hellip;';
		}
		return $hellip;
	}

	/**
	 * Adds comment row action.
	 *
	 * @since 2.0
	 *
	 * @param array  $actions The actions being displayed for the comment entry
	 * @param object $comment The comment being displayed
	 * @return array The actions for the comment entry
	 */
	public static function comment_row_action( $actions, $comment ) {
		$excerpt = get_comment_excerpt( $comment->comment_ID );

		$start_expanded = self::is_comment_initially_expanded( $comment );
		$excerpt_full   = $start_expanded ? 'style="display:none;"' : '';
		$excerpt_short  = $start_expanded ? '' : 'style="display:none;"';

		// Only show the action links if the comment was excerpted
		if ( self::is_text_excerpted( $excerpt ) ) {
			$links = '<a href="#" class="c2c_edrc_more hide-if-no-js" title="' . __( 'Show full comment', 'c2c_edrc' ) . '" ' . $excerpt_full . '>' . __( 'Show more', 'c2c_edrc' ). '</a>';
			$links .= '<a href="#" class="c2c_edrc_less hide-if-no-js" title="' . __( 'Show excerpt', 'c2c_edrc' ). '" ' . $excerpt_short . '>' . __( 'Show less', 'c2c_edrc' ) . '</a>';
			$actions[] = $links;
		}
		return $actions;
	}

	/**
	 * Returns class name to be used for specific comment
	 *
	 * @since 1.3
	 *
	 * @param int|string|null $comment_id The comment ID (or null to get the ID for the current comment)
	 * @return string The class
	 */
	private static function get_comment_class( $comment_id = null ) {
		if ( ! $comment_id ) {
			global $comment;
			$comment_id = $comment->comment_ID;
		}
		return "excerpt-$comment_id";
	}

	/**
	 * Modifies a comment excerpt to add the full comment so it is available for expansion.
	 *
	 * @param string $excerpt Excerpt
	 * @return string The $excerpt modified to have full comment when applicable
	 */
	public static function expandable_comment_excerpts( $excerpt ) {
		global $comment;
		if ( self::is_text_excerpted( $excerpt ) ) {
			$replace = self::get_ellipsis( $excerpt );
			$body    = apply_filters( 'comment_text', apply_filters( 'get_comment_text', $comment->comment_content ), '40' );
			$class   = self::get_comment_class( $comment->comment_ID );

			$start_expanded = self::is_comment_initially_expanded( $comment );
			$excerpt_full   = $start_expanded ? '' : 'style="display:none;"';
			$excerpt_short  = $start_expanded ? 'style="display:none;"' : '';

			$links = '';
			if ( false == self::$_has_output_all_links ) {
				// These links apply to the entire widget. Due to lack of hooks in WP, they
				// are being embedded here with the intent of being relocated via JS.
				$links .= '<ul class="subsubsub c2c_edrc_all">';
				$links .= '<li><a href="#" class="c2c_edrc_more_all hide-if-no-js" title="' . __( 'Show all comments in full', 'c2c_edrc' ) . '">' . __( '&#x25bc; Expand all', 'c2c_edrc' ). '</a> |</li>';
				$links .= '<li><a href="#" class="c2c_edrc_less_all hide-if-no-js" title="' . __( 'Show all comments as excerpts', 'c2c_edrc' ). '">' . __( '&#x25b2; Collapse all', 'c2c_edrc' ) . '</a></li>';
				$links .= '</ul>';
				self::$_has_output_all_links = true;
			}

			$extended = <<<HTML
			<div class='c2c_edrc'>
				<div class='{$class}-short excerpt-short' {$excerpt_short}>
					$excerpt
				</div>
				<div class='{$class}-full excerpt-full' {$excerpt_full}>
					$body
					$links
				</div>
			</div>

HTML;

			$excerpt = preg_replace( '/' . preg_quote( $replace ) . '$/', $excerpt, $extended );
		}
		return $excerpt;
	}

} // end c2c_ExpandableDashboardRecentComments

c2c_ExpandableDashboardRecentComments::init();

endif; // end if !class_exists()
