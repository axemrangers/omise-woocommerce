<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Subscriptions compatibility.
 * 
 * largely based on stripe's subscription compat
 * 
 * @since 4.21.1
 */
trait WC_Omise_Subscriptions_Trait {


	use WC_Omise_Subscriptions_Utils_Trait;

	/**
	 * Initialize subscription support and hooks.
	 *
	 */
	public function maybe_init_subscriptions() {
    if ( ! $this->is_subscriptions_enabled() ) {
      return;
    }

		$this->supports = array_merge( # TODO register support for all
			$this->supports,
			[
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions',
			]
		);

    add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, [ $this, 'scheduled_subscription_payment' ], 10, 2 );


  }
	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param $amount_to_charge float The amount to charge.
	 * @param $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) { // TODO subscription payment
		$this->process_subscription_payment( $amount_to_charge, $renewal_order, true, false );
	}

  /**
   * 
	 *
	 * @param float  $amount
	 * @param mixed  $renewal_order
	 * @param bool   $retry Should we retry the process?
	 * @param object $previous_error
   */
	public function process_subscription_payment( $amount, $renewal_order, $retry = true, $previous_error = false ) {
    try{
      $order_id = $renewal_order->get_id();


    }
  
  }
}