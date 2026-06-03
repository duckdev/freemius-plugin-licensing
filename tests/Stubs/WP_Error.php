<?php
/**
 * Minimal WP_Error stub for unit tests.
 *
 * Matches the shape the library actually uses: a code, a message,
 * and the {@see is_wp_error()} sentinel.
 *
 * @package DuckDev\Freemius\Tests
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private $data;

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}
