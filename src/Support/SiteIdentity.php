<?php
/**
 * Site identity helper.
 *
 * Generates a deterministic UID for the current site. Used by the
 * licensing flow to tie an activation to a specific host without
 * storing the site URL directly: the UID is sent to Freemius at
 * activation time and re-checked at deactivation time to make sure
 * the request originates from the same install.
 *
 * Kept as its own collaborator (rather than a static helper) so the
 * UID can be stubbed in unit tests for {@see \DuckDev\Freemius\Services\License}.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Support
 */

namespace DuckDev\Freemius\Support;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Class SiteIdentity.
 */
class SiteIdentity {

	/**
	 * Compute a stable UID for the current site.
	 *
	 * The UID is the md5 of `host-blog_id[-path]`, so multisite
	 * subsites get distinct UIDs and subdirectory installs do not
	 * collide with the root site.
	 *
	 * @since 2.0.0
	 *
	 * @return string 32-character hexadecimal UID.
	 */
	public function get_uid(): string {
		$blog_id        = get_current_blog_id();
		$site_url       = get_site_url( $blog_id );
		$site_url_parts = wp_parse_url( $site_url );

		$data = array( $site_url_parts['host'] ?? '', $blog_id );
		if ( isset( $site_url_parts['path'] ) ) {
			$data[] = $site_url_parts['path'];
		}

		return md5( implode( '-', $data ) );
	}
}
