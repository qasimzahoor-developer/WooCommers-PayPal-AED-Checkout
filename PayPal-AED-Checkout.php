<?php
/**
 * @package PayPal_AED_Checkout
 * @version 1.0.1
 */
/*
Plugin Name: PayPal AED Checkout
Plugin URI: https://github.com/qasimzahoor-developer/WooCommers-PayPal-AED-Checkout
Description: AED to USD on Paypal Checkout Plugin.
Author: Qasim Zahoor
Version: 1.0.1
Author URI: https://github.com/qasimzahoor-developer
*/
add_action( 'woocommerce_thankyou', 'ced_change_order_status' );
function ced_change_order_status( $order_id ){
   if( ! $order_id ) return;
   $order = wc_get_order( $order_id );
   $order->update_status( 'processing' );
} 
add_filter( 'woocommerce_paypal_supported_currencies', 'add_new_paypal_valid_currency');
function add_new_paypal_valid_currency( $currencies ) { 
	array_push ( $currencies, get_woocommerce_currency() );  
	return $currencies;    
} //add_filter( 'woocommerce_currency', 'change_woocommerce_currency' );
add_action( 'woocommerce_before_checkout_process', 'initiate_order' , 10, 1 );
    function initiate_order($array){
		$chosen_payment_method = WC()->session->get('chosen_payment_method');
		if(strpos(strtolower($chosen_payment_method), 'paypal') === false) return;
		add_filter( 'woocommerce_currency', 'change_woocommerce_currency' );
		add_filter('woocommerce_product_get_price', 'custom_price' , 99, 2 );
		add_filter('woocommerce_product_get_regular_price', 'custom_price' , 99, 2 );
		add_filter('woocommerce_product_variation_get_regular_price', 'custom_price' , 99, 2 );
		add_filter('woocommerce_product_variation_get_price', 'custom_price' , 99, 2 );
		add_filter('woocommerce_variation_prices_price', 'custom_variable_price', 99, 3 );
		add_filter('woocommerce_variation_prices_regular_price', 'custom_variable_price', 99, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', 'add_price_multiplier_to_variation_prices_hash', 99, 3 );
		add_filter( 'woocommerce_package_rates', 'woocommerce_package_rates' );

    }
function get_price_multiplier() {
	$arr_rate = get_option( 'paypal_aed_to_usd_rate' );
	if(!isset($arr_rate['date']) || $arr_rate['date'] !== date('Y-m-d')){
		$rate = file_get_contents("https://free.currconv.com/api/v7/convert?q=AED_USD&compact=ultra&apiKey=13d6a6d03a1137ed9f9a");
		$rate = json_decode($rate, true);
		if(!((float)$rate['AED_USD'] > 0)){
			throw new Exception( "Exchange Rate issue, Please choose other payment method or contact our support");
		}
		$arr_rate = ['rate'=>(float)$rate['AED_USD'], 'date'=> date('Y-m-d')];
		if(!add_option('paypal_aed_to_usd_rate', $arr_rate)){
			update_option('paypal_aed_to_usd_rate', $arr_rate);
		}

	}
    return $arr_rate['rate']; 
}
function custom_price( $price, $product ) {
    return (float) $price * get_price_multiplier();
}

function custom_variable_price( $price, $variation, $product ) {
    return (float) $price * get_price_multiplier();
}

function add_price_multiplier_to_variation_prices_hash( $price_hash, $product, $for_display ) {
    $price_hash[] = get_price_multiplier();
    return $price_hash;
}
function change_woocommerce_currency( $currency ) {
    return 'USD';
}
function woocommerce_package_rates( $rates ) {

    foreach($rates as $key => $rate ) {
        $rates[$key]->cost = $rates[$key]->cost * get_price_multiplier();
    } 

    return $rates;
}
add_filter('woocommerce_available_payment_gateways', 'woocommerce_available_payment_gateways');
function woocommerce_available_payment_gateways( $available_gateways ) {
	if (! is_checkout() ) return $available_gateways;
	$chosen_payment_method = WC()->session->get('chosen_payment_method');
	if(strpos(strtolower($chosen_payment_method), 'paypal') === false) $available_gateways;
	
	if (array_key_exists('paypal',$available_gateways)) {
		$conv_rate = get_price_multiplier();
		$cart_total =  floatval( preg_replace( '#[^\d.]#', '', WC()->cart->total ) );
		$conv_cart_total =  number_format(floatval($cart_total*$conv_rate), 2, '.', '');
		$available_gateways['paypal']->description = __( 
				$available_gateways['paypal']->description.
				'<div style="background:#F15D2E; color:#fff;">You will be charged: Total AED '.$cart_total.' * USD Conversion rate '.$conv_rate.' = '.$conv_cart_total.' USD Total</div>', 'woocommerce' 
			);
	}
	return $available_gateways;
}