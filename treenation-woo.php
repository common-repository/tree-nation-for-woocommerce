<?php
/**
 * Tree-Nation Integration
 *
 * @version 1.5
 * @package TreenationWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC Integration Tree-Nation
 *
 * @since 3.9.0
 */
class WC_Treenationwoo_Integration extends WC_Integration {

	private $wh; // will hold the WooCommerce webhook info

	/**
	 * Initialize the integration.
	 */
	public function __construct() {
		$this->id                 = 'treenationwoo';
		$this->method_title       = __('Tree-Nation for WooCommerce', 'treenation-for-woo');
		$this->method_description = __('An integration for utilizing WooCommerce store to plant trees on Tree-Nation. Visit <a href="https://tree-nation.com" target="_blank">tree-nation.com</a> to learn more about our integrations.', 'treenation-for-woo');
		$this->wh = null;

		$this->init_settings();

		if (is_admin()) {
			$this->init_form_fields();

			// get current webhooks
			$a = WC_Data_Store::load( 'webhook' );
			$webhooks = $a->search_webhooks();
			$items = array_map( 'wc_get_webhook', $webhooks );
			$this->wh = null;
            foreach ($items as $wh) {
                if ($wh->get_name() == 'Tree-Nation' || strpos($wh->get_delivery_url(), 'tree-nation.com/woocommerce-webhook') !== false) {
                    $this->wh = $wh;
                }
            }

			$tnid = '';
			$advanced = '';
			// extract info from found webhook
			if ($this->wh) {
			    $url = $this->wh->get_delivery_url();
			    $needle = 'https://tree-nation.com/woocommerce-webhook?treenation_id=';
			    if (strpos($url, $needle) !== false) $tnid = substr($url, strlen($needle));
                else $advanced = $url;
            }
			$this->settings['treenation_id']  = $tnid;
			$this->settings['treenation_url'] = $advanced;

			add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'admin_notices', array( $this, 'checks' ) );
		}
	}

	public function process_admin_options() {
        // get current webhooks
        $a = WC_Data_Store::load( 'webhook' );
        $webhooks = $a->search_webhooks();
        $items = array_map( 'wc_get_webhook', $webhooks );
        $this->wh = null;
		$found = null;
		foreach ($items as $wh) {
			// delete duplicated
			if ($found && $wh->get_name() == 'Tree-Nation' || strpos($wh->get_delivery_url(), 'https://tree-nation.com/woocommerce-webhook') !== false) {
				$wh->delete();
				continue;
			}
			if ($wh->get_name() == 'Tree-Nation' || strpos($wh->get_delivery_url(), 'https://tree-nation.com/woocommerce-webhook') !== false) {
				$found = $wh;
				break;
			}
		}
		$this->wh = $found;

        $post_data = $this->get_post_data();
		$treenation_id = $post_data['woocommerce_treenationwoo_treenation_id'];
		$url = $post_data['woocommerce_treenationwoo_treenation_url'];
		if (empty($url) && empty($treenation_id)) {
		    return parent::process_admin_options();
        }

		if (empty($url)) $url = 'https://tree-nation.com/woocommerce-webhook?treenation_id='.$treenation_id;
		if (!$this->wh) {
			// create
			$this->wh = new WC_Webhook();
		}
		$this->wh->set_topic('order.updated');
		$this->wh->set_name('Tree-Nation');
		$this->wh->set_api_version(2);
		$this->wh->set_user_id(get_current_user_id());
		$this->wh->set_pending_delivery(false);
		$this->wh->set_status('active');
		$this->wh->set_delivery_url($url);
		$this->wh->save();

		return parent::process_admin_options();
	}

	/**
	 * Initializes the settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'treenation_id' => array(
				'title'       => __( 'Tree-Nation ID', 'woocommerce' ),
				'type'        => 'text',
				'description' => __(
                    'Enter here the ID provided by Tree-Nation.',
                    'treenation-for-woo'
                ),
				'desc_tip'    => false,
				'default'     => '',
			),
			'treenation_url' => array(
				'title'       => __( 'Advanced parameters', 'treenation-for-woo' ),
				'type'        => 'text',
				'description' => __(
                    'Leave this field empty unless you’ve been provided with a specific value.',
                    'treenation-for-woo'
                ),
				'desc_tip'    => false,
				'default'     => '',
			),
		);
	}

	/**
	 * Check for api key and any other API errors
	 **/
	function checks() {
		// Check required fields
		if ( ! $this->wh ) {
			echo $this->get_message_html(
				sprintf(
					__( '%1$sTree-Nation for WooCommerce is almost ready.%2$s To complete your configuration, %3$scomplete the setup steps%4$s.', 'treenation-for-woocommerce' ),
					'<strong>',
					'</strong>',
					'<a href="' . esc_url( WOOCOMMERCE_TREENATION_PLUGIN_SETTINGS_URL ) . '">',
					'</a>'
				),
				'info'
			);
		}

		// WooCommerce 3.x upgrade warning
		$major_version = substr(WC()->version, 0,1);
		if ( $this->wh && $major_version < 3 ) {
			echo $this->get_message_html(
				sprintf( __( 'Tree-Nation integration may not work correctly in WooCommerce version %1$s. Please upgrade to WooCommerce 3.', 'treenation-for-woocommerce' ), WC()->version ),
				'warning'
			);
		}
	}

	/**
	 * Get message
	 *
	 * @return string Error
	 */
	private function get_message_html( $message, $type = 'error' ) {
		ob_start();

		?>
		<div class="notice is-dismissible notice-<?php echo $type; ?>">
			<p><?php echo $message; ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Checks to make sure that the license key is valid.
	 *
	 * @param string $key The key of the field.
	 * @param mixed  $value The value of the field.
	 * @return mixed
	 * @throws Exception When the license key is invalid.
	 */
	public function validate_treenation_id_field( $key, $value ) {
		if ( !empty( $value ) && strlen( $value ) < 2 ) {
			WC_Admin_Settings::add_error( esc_html__( 'It looks like you made a mistake with the Tree-Nation ID field. Make sure you typed it correctly.', 'treenation-for-woo' ) );
		}

		return $value;
	}

}
