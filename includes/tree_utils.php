<?php
/**
 * Copyright (c) Tree-Nation. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package TreenationWoo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Treenationwoo_Utils' ) ) :

	/**
	 * FB Graph API helper functions
	 */
	class WC_Treenationwoo_Utils {

		const PLUGIN_VERSION        = '1.5';  // Change it in `treenation-for-woocommerce.php` also

		/**
		 * Returns true if WooCommerce plugin found.
		 *
		 * @access public
		 * @return bool
		 */
		public static function isWoocommerceIntegration() {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Helper log function for debugging
		 */
		public static function log( $message ) {
			if ( WP_DEBUG === true ) {
				if ( is_array( $message ) || is_object( $message ) ) {
					error_log( json_encode( $message ) );
				} else {
					error_log( sanitize_textarea_field( $message ) );
				}
			}
		}

	}

endif;
