<?php
defined( 'ABSPATH' ) or die( "No direct script access allowed." );

if (  class_exists( 'Omise_FBBot_WooCommerce') ) {
  return;
}

class Omise_FBBot_WooCommerce {
	public static function check_order_status( $order_id ) {
	// Order status in woocommerce
    // pending, processing, on-hold, completed, cancelled, refunded, failed

    $order = wc_get_order( $order_id );

    if (! $order ) {
    	return NULL;
    }

    $status = $order->get_status();

    return $status;
	}

	public static function update_order_status( $order_id, $charge ) {
		$order = new WC_Order( $order_id );

		switch ( $charge->status ) {
			case 'failed':
				$order->update_status( 'failed' );
				break;
			
			case 'pending':
				$order->update_status( 'on-hold' );
				break;

			case 'reversed':
				$order->update_status( 'refunded' );
				break;

			case 'successful':
				$order->update_status( 'processing' );
				break;

			default:
				$order->update_status( 'on-hold' );
				break;
		}
		
		if ( $charge->paid ) {
			$order->update_status( 'completed' );
		}
	}

	public static function create_order( $params ) {
		$product_id = $params['product_id'];
		$messenger_id = $params['messenger_id'];
		$customer_email = $params['customer_email'];

    	$user = Omise_FBBot_User_Service::get_user( $messenger_id );
    
    	if ( ! $user )
    		return;

    	$address = array(
    		'first_name' => $user['first_name'],
    		'last_name' => $user['last_name'],
    		'email'	=> $customer_email
    	);

    	$note = 'Purchase via Messenger app by user : ' . $messenger_id;

    	$order = wc_create_order();
    	$order->add_product( get_product( $product_id ), 1 );
    	$order->set_address( $address, 'billing' );
    	$order->add_order_note( $note );
    	$order->calculate_totals();

    	return $order;
	}

}