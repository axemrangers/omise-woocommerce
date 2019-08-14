<?php
defined( 'ABSPATH' ) or die( 'No direct script access allowed.' );

function register_omise_billpayment_tesco() {
	require_once dirname( __FILE__ ) . '/class-omise-payment.php';

	if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'Omise_Payment_Billpayment_Tesco' ) ) {
		return;
	}

	/**
	 * @since 3.7
	 */
	class Omise_Payment_Billpayment_Tesco extends Omise_Payment {
		public function __construct() {
			parent::__construct();

			$this->id                 = 'omise_billpayment_tesco';
			$this->has_fields         = false;
			$this->method_title       = __( 'Omise Bill Payment: Tesco', 'omise' );
			$this->method_description = wp_kses(
				__( 'Accept payments through <strong>Tesco Bill Payment</strong> via Omise payment gateway.', 'omise' ),
				array( 'strong' => array() )
			);

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'display_barcode' ) );
			add_action( 'woocommerce_email_after_order_table', array( $this, 'email_barcode' ) );
			add_action( 'omise_checkout_assets', array( $this, 'omise_billpayment_checkout_assets' ) );
		}

		/**
		 * @see WC_Settings_API::init_form_fields()
		 * @see woocommerce/includes/abstracts/abstract-wc-settings-api.php
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'omise' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Omise Tesco Bill Payment', 'omise' ),
					'default' => 'no'
				),

				'title' => array(
					'title'       => __( 'Title', 'omise' ),
					'type'        => 'text',
					'description' => __( 'This controls the title the user sees during checkout.', 'omise' ),
					'default'     => __( 'Bill Payment: Tesco', 'omise' ),
				),

				'description' => array(
					'title'       => __( 'Description', 'omise' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description the user sees during checkout.', 'omise' )
				),
			);
		}

		/**
		 * @see Omise_Payment::omise_checkout_assets()
		 */
		public function omise_billpayment_checkout_assets() {
			wp_enqueue_style( 'omise-billpayment-print', plugins_url( '../../assets/css/omise-billpayment-print.css', __FILE__ ), array(), OMISE_WOOCOMMERCE_PLUGIN_VERSION );
		}

		/**
		 * @inheritdoc
		 */
		public function charge( $order_id, $order ) {
			$total      = $order->get_total();
			$currency   = $order->get_order_currency();
			$return_uri = add_query_arg(
				array( 'wc-api' => 'omise_billpayment_tesco_callback', 'order_id' => $order_id ), home_url()
			);
			$metadata   = array_merge(
				apply_filters( 'omise_charge_params_metadata', array(), $order ),
				array( 'order_id' => $order_id ) // override order_id as a reference for webhook handlers.
			);

			return OmiseCharge::create( array(
				'amount'      => Omise_Money::to_subunit( $total, $currency ),
				'currency'    => $currency,
				'description' => apply_filters( 'omise_charge_params_description', 'WooCommerce Order id ' . $order_id, $order ),
				'source'      => array( 'type' => 'bill_payment_tesco_lotus' ),
				'return_uri'  => $return_uri,
				'metadata'    => $metadata
			) );
		}

		/**
		 * @inheritdoc
		 */
		public function result( $order_id, $order, $charge ) {
			if ( 'failed' == $charge['status'] ) {
				return $this->payment_failed( $charge['failure_message'] . ' (code: ' . $charge['failure_code'] . ')' );
			}

			if ( 'pending' == $charge['status'] ) {
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
			}

			return $this->payment_failed(
				sprintf(
					__( 'Please feel free to try submitting your order again, or contact our support team if you have any questions (Your temporary order id is \'%s\')', 'omise' ),
					$order_id
				)
			);
		}

		/**
		 * @param int|WC_Order $order
		 * @param string       $context  pass 'email' value through this argument only for 'sending out an email' case.
		 */
		public function display_barcode( $order, $context = 'view' ) {
			if ( ! $this->load_order( $order ) ) {
				return;
			}

			$charge_id          = $this->get_charge_id_from_order();
			$charge             = OmiseCharge::retrieve( $charge_id );
			$barcode_svg        = file_get_contents( $charge['source']['references']['barcode'] );
			$barcode_html       = $this->barcode_svg_to_html( $barcode_svg );
			$barcode_ref_number = sprintf(
				'| &nbsp; %1$s &nbsp; 00 &nbsp; %2$s &nbsp; %3$s &nbsp; %4$s',
				$charge['source']['references']['omise_tax_id'],
				$charge['source']['references']['reference_number_1'],
				$charge['source']['references']['reference_number_2'],
				$charge['amount']
			);
			?>

			<div class="omise omise-billpayment-tesco-details" <?php echo 'email' === $context ? 'style="margin-bottom: 4em; text-align:center;"' : ''; ?>>
				<p><?php echo __( 'Use this barcode to pay at Tesco Lotus.', 'omise' ); ?></p>
				<div class="omise-billpayment-tesco-barcode-wrapper">
					<?php echo $barcode_html; ?>
				</div>
				<small class="omise-billpayment-tesco-reference-number">
					<?php echo $barcode_ref_number; ?>
				</small>

				<?php if ( 'email' !== $context ) : ?>
					<div class="omise-billpayment-tesco-print-button">
						<button onClick="window.print()" class="button button-primary">Print barcode</button>
					</div>
				<?php endif; ?>
			</div>

			<!-- Display for printing only. -->
			<!-- Hidden by assets/css/omise-billpayment-print.css. -->
			<?php if ( 'email' !== $context ) : ?>

				<div class="omise-billpayment-tesco-print-detail">
					<div>
						<h2><?php echo apply_filters( 'woocommerce_thankyou_order_received_text', __( 'Thank you. Your order has been received.', 'woocommerce' ), $order ); ?></h2>
					</div>

					<div class="omise-billpayment-tesco-print-order-overview">
						<div class="omise-billpayment-tesco-print-order-overview-item">
							<?php _e( 'Order number:', 'woocommerce' ); ?>
							<br/><strong><?php echo $this->order()->get_order_number(); ?></strong>
						</div>

						<div class="omise-billpayment-tesco-print-order-overview-item">
							<?php _e( 'Date:', 'woocommerce' ); ?>:
							<br/><strong><?php echo wc_format_datetime( $this->order()->get_date_created() ); ?></strong>
						</div>

						<?php if ( is_user_logged_in() && $this->order()->get_user_id() === get_current_user_id() && $this->order()->get_billing_email() ) : ?>
							<div class="omise-billpayment-tesco-print-order-overview-item">
								<?php _e( 'Email:', 'woocommerce' ); ?>:
								<br/><strong><?php echo $this->order()->get_billing_email(); ?></strong>
							</div>
						<?php endif; ?>

						<div class="omise-billpayment-tesco-print-order-overview-item">
							<?php _e( 'Total:', 'woocommerce' ); ?>:
							<br/><strong><?php echo $this->order()->get_formatted_order_total(); ?></strong>
						</div>
					</div>

					<?php if ( $this->order()->get_payment_method_title() ) : ?>
						<div class="omise-billpayment-tesco-print-order-payment-method">
							<?php _e( 'Payment method:', 'woocommerce' ); ?>
							<br/><strong><?php echo wp_kses_post( $this->order()->get_payment_method_title() ); ?></strong>
						</div>
					<?php endif; ?>

					<p class="omise-billpayment-tesco-print-barcode-message"><?php _e( 'Use this barcode to pay at Tesco Lotus.', 'omise' ); ?></p>

					<div class="omise-billpayment-tesco-print-barcode-wrapper">
						<?php echo $barcode_html; ?>
					</div>

					<div class="omise-billpayment-tesco-print-reference-number">
						<small><?php echo $barcode_ref_number; ?></small>
					</div>
				</div>

				<script type="text/javascript">
					let omise_billpayment_print_detail        = document.getElementsByClassName( 'omise-billpayment-tesco-print-detail' );
					let cloned_omise_billpayment_print_detail = omise_billpayment_print_detail[0].cloneNode( true );

					document.body.appendChild( cloned_omise_billpayment_print_detail );

					omise_billpayment_print_detail[0].parentNode.removeChild( omise_billpayment_print_detail[0] );
				</script>

			<?php endif;
		}

		/**
		 * @param WC_Order $order
		 *
		 * @see   woocommerce/templates/emails/email-order-details.php
		 * @see   woocommerce/templates/emails/plain/email-order-details.php
		 */
		public function email_barcode( $order ) {
			$this->display_barcode( $order, 'email' );
		}

		/**
		 * Convert a given SVG Bill Payment Tesco's barcode to HTML format.
		 *
		 * Note that the SVG barcode contains with the following structure:
		 *
		 * <?xml version="1.0" encoding="UTF-8"?>
		 * <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="515px" height="90px" viewBox="0 0 515 90" version="1.1" preserveAspectRatio="none">
		 *   <title>** reference number **</title>
		 *   <g id="canvas">
		 *     <rect x="0" y="0" width="515px" height="90px" fill="#fff" />
		 *     <g id="barcode" fill="#000">
		 *       <rect x="20" y="20" width="2px" height="50px" />
		 *       ... (repeat <rect> node for displaying barcode) ...
		 *       <rect x="493" y="20" width="2px" height="50px" />
		 *     </g>
		 *   </g>
		 * </svg>
		 *
		 * The following code in this method is to read all <rect> nodes' attributes under the <g id="barcode"></g>
		 * in order to replicate the barcode in HTML <div></div> element.
		 *
		 * @param  string $barcode_svg
		 *
		 * @return string  of a generated Bill Payment Tesco's barcode in HTML format.
	     */
		public function barcode_svg_to_html( $barcode_svg ) {
			$xml       = new SimpleXMLElement( $barcode_svg );
			$xhtml     = new DOMDocument();
			$prevX     = 0;
			$prevWidth = 0;

			$div_wrapper = $xhtml->createElement( 'div' );
			$div_wrapper->setAttribute( 'class', 'omise-billpayment-tesco-barcode' );
			$div_wrapper->setAttribute( 'style', 'background-color: #ffffff;' );

			// Read data from all <rect> nodes.
			foreach ( $xml->g->g->children() as $rect ) {
				$attributes = $rect->attributes();
				$width      = $attributes['width'];
				$margin     = ( $attributes['x'] - $prevX - $prevWidth ) . 'px';

				// Set HTML attributes based on <rect> node's attributes.
				$div_rect = $xhtml->createElement( 'div' );
				$div_rect->setAttribute( 'style', "float: left; position: relative; height: 50px; border-left: $width solid #000000; width: 0; margin-left: $margin" );
				$div_wrapper->appendChild( $div_rect );

				$prevX     = $attributes['x'];
				$prevWidth = $attributes['width'];
			}

			// Add an empty <div></div> element to clear those floating elements.
			$div = $xhtml->createElement( 'div' );
			$div->setAttribute( 'style', 'clear:both' );
			$div_wrapper->appendChild( $div );

			$xhtml->appendChild( $div_wrapper );
			return $xhtml->saveXML( null, LIBXML_NOEMPTYTAG );
		}
	}

	if ( ! function_exists( 'add_omise_billpayment_tesco' ) ) {
		/**
		 * @param  array $methods
		 *
		 * @return array
		 */
		function add_omise_billpayment_tesco( $methods ) {
			$methods[] = 'Omise_Payment_Billpayment_Tesco';
			return $methods;
		}

		add_filter( 'woocommerce_payment_gateways', 'add_omise_billpayment_tesco' );
	}
}