<?php
/**
 * Base library exception.
 *
 * Services in this library return {@see \WP_Error} from their public
 * methods (the WordPress convention for recoverable failures). This
 * exception class exists for the internal programmer-error case —
 * e.g. attempting to sign a request with an empty key pair — where
 * throwing is more appropriate than silently returning an error.
 *
 * Currently unused by the bundled services; provided as a stable
 * base type so consumers and future internal code paths have
 * somewhere consistent to extend from.
 *
 * @link       https://duckdev.com/
 * @license    http://www.gnu.org/licenses/ GNU General Public License
 * @author     Joel James <me@joelsays.com>
 * @since      1.0.0
 * @package    Freemius
 * @subpackage Exceptions
 */

namespace DuckDev\Freemius\Exceptions;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

use Exception;

/**
 * Class FreemiusException.
 */
class FreemiusException extends Exception {
}
