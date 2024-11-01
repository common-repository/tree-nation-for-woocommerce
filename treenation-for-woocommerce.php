<?php
/**
 * Copyright (c) Tree-Nation. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * Plugin Name: Tree-Nation for WooCommerce
 * Description: This integration will allow you to offer a tree each time a customer buys a product.

 * Author: Tree-Nation
 * Author URI: https://tree-nation.com/
 * Version: 1.7
 * Text Domain: treenation-for-woocommerce
 * WC requires at least: 3.0.0
 * WC tested up to: 6.5.5
 *
 * @package TreenationWoo
 */


if ( ! class_exists( 'WC_Treenationwoo' ) ) :
	include_once 'includes/tree_utils.php';

	class WC_Treenationwoo {

		// Change it above as well
		const PLUGIN_VERSION = WC_Treenationwoo_Utils::PLUGIN_VERSION;

		/**
		 * Construct the plugin.
		 */
		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Initialize the plugin.
		 */
		public function init() {
			if ( is_admin() ) {
			    $route = 'plugin_action_links_' . plugin_basename( __FILE__ );
				add_filter( $route, array( $this, 'add_settings_link' ) );
			}

			if ( WC_Treenationwoo_Utils::isWoocommerceIntegration() ) {
				if ( ! defined( 'WOOCOMMERCE_TREENATION_PLUGIN_SETTINGS_URL' ) ) {
					define( 'WOOCOMMERCE_TREENATION_PLUGIN_SETTINGS_URL', get_admin_url() . '/admin.php?page=wc-settings&tab=integration' . '&section=treenationwoo' );
				}
				include_once 'treenation-woo.php';

				// Register WooCommerce integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_woocommerce_integration' ) );
			}
		}

		public function add_settings_link( $links ) {
			$settings = array( 'settings' => sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=integration&section=treenationwoo' ), 'Settings' ) );
			return array_merge( $settings, $links );
		}

		public function wp_debug_display_error() {
?>
	<div class="error below-h3">
	  <p>
			<?php
    			printf( __( 'To use Tree-Nation for WooCommerce, please disable WP_DEBUG_DISPLAY in your wp-config.php file. Contact your server administrator for more assistance.', 'treenation-for-woocommerce' ) );
			?>
	  </p>
	</div>
			<?php
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_woocommerce_integration( $integrations ) {
			$integrations[] = 'WC_Treenationwoo_Integration';
			return $integrations;
		}
	}

	$WC_Treenationwoo = new WC_Treenationwoo( __FILE__ );

endif;

function findExistingConfig()
{
	$a = WC_Data_Store::load( 'webhook' );
	$webhooks = $a->search_webhooks();
	$items = array_map( 'wc_get_webhook', $webhooks );
	$found = null;
	foreach ($items as $wh) {
		// delete duplicated
	    if ($found && $wh->get_name() == 'Tree-Nation' || strpos($wh->get_delivery_url(), 'https://tree-nation.com/woocommerce-webhook') !== false) {
	        $wh->delete();
	        continue;
      	}
		if ($wh->get_name() == 'Tree-Nation' || strpos($wh->get_delivery_url(), 'https://tree-nation.com/woocommerce-webhook') !== false) {
			$found = $wh;
		}
	}
	return $found;
}

function treenationwoo_activate_webhook()
{
    // Installed version number
    update_option('treenationwoo_version', WC_Treenationwoo::PLUGIN_VERSION);

    $found = findExistingConfig();
	if ($found) {
	    // correct wrong url
		$url = $found->get_delivery_url();
		$wrong_needle = 'https://tree-nation.com/woocommerce-webhook/?treenation_id='; // correct the wrong url
		if (strpos($url, $wrong_needle) !== false) {
            $correct_needle = 'https://tree-nation.com/woocommerce-webhook?treenation_id=';
    		$found->set_delivery_url($correct_needle . substr($url, strlen($wrong_needle)));
		}
		// set active
		$found->set_status('active');
		$found->save();
	}
}
register_activation_hook( __FILE__,'treenationwoo_activate_webhook' );

// Checks the version number
function treenationwoo_check_version() {
    if (WC_Treenationwoo::PLUGIN_VERSION !== get_option('treenationwoo_version'))
        treenationwoo_activate_webhook();
}
add_action('plugins_loaded', 'treenationwoo_check_version');


add_action( 'before_woocommerce_init', function() {
       if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
               \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
       }
});

function treenationwoo_disable_webhook()
{
	$found = findExistingConfig();
	if ($found) {
		$found->set_status('disabled');
		$found->save();
	}
}
register_deactivation_hook( __FILE__, 'treenationwoo_disable_webhook' );
