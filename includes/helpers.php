<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
//Debug
if (!function_exists('vardump')) {
    function vardump( $string ) {
        var_dump( '<pre>' );
        var_dump( $string );
        var_dump( '</pre>' );
    }
}

//#Change Woo function wc_cart_totals_shipping_html()
function tsc_cart_totals_shipping_html(){
        $packages = WC()->shipping->get_packages();
        $first    = false;
        $k = 1;

	    foreach ( $packages as $i => $package ) {
		    $chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
		    $product_names = array();

		    if ( sizeof( $packages ) > 1 ) {
			    foreach ( $package['contents'] as $item_id => $values ) {
				    $product_names[ $item_id ] = $values['data']->get_name() . ' &times;' . $values['quantity'];
			    }
			    $product_names = apply_filters( 'woocommerce_shipping_package_details_array', $product_names, $package );
		    }

                    $first = ($k == sizeof( $packages )) ? true : false;

		    wc_get_template( 'cart/cart-shipping.php', array(
			    'package'                  => $package,
			    'available_methods'        => $package['rates'],
			    'show_package_details'     => sizeof( $packages ) > 1,
			    'show_shipping_calculator' => is_cart() && $first,
			    'package_details'          => implode( ', ', $product_names ),
			    // @codingStandardsIgnoreStart
			    'package_name'             => apply_filters( 'woocommerce_shipping_package_name', sprintf( _nx( 'Shipping', 'Shipping %d', ( $i + 1 ), 'shipping packages', 'woocommerce' ), ( $i + 1 ) ), $i, $package ),
			    // @codingStandardsIgnoreEnd
			    'index'                    => $i,
			    'chosen_method'            => $chosen_method,
		    ) );		

            $k++;
	    }
}

/**
* Find a matching zone IDs for a given package. (Attention: Not ID as in Woo)
*
* @param  object $package
* @return array
*/
function tsc_get_zone_ids_from_packages( $package ) {
       global $wpdb;

       $country          = strtoupper( wc_clean( $package['destination']['country'] ) );
       $state            = strtoupper( wc_clean( $package['destination']['state'] ) );
       $continent        = strtoupper( wc_clean( WC()->countries->get_continent_code_for_country( $country ) ) );
       $postcode         = wc_normalize_postcode( wc_clean( $package['destination']['postcode'] ) );

       // Work out criteria for our zone search
       $criteria   = array();
       $criteria[] = $wpdb->prepare( "( ( location_type = 'country' AND location_code = %s )", $country );
       $criteria[] = $wpdb->prepare( "OR ( location_type = 'state' AND location_code = %s )", $country . ':' . $state );
       $criteria[] = $wpdb->prepare( "OR ( location_type = 'continent' AND location_code = %s )", $continent );
       $criteria[] = "OR ( location_type IS NULL ) )";

       // Postcode range and wildcard matching
       $postcode_locations = $wpdb->get_results( "SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'postcode';" );

       if ( $postcode_locations ) {
               $zone_ids_with_postcode_rules = array_map( 'absint', wp_list_pluck( $postcode_locations, 'zone_id' ) );
               $matches                      = wc_postcode_location_matcher( $postcode, $postcode_locations, 'zone_id', 'location_code', $country );
               $do_not_match                 = array_unique( array_diff( $zone_ids_with_postcode_rules, array_keys( $matches ) ) );

               if ( ! empty( $do_not_match ) ) {
                       $criteria[] = "AND zones.zone_id NOT IN (" . implode( ',', $do_not_match ) . ")";
               }
       }

       // Get matching zones
       return $wpdb->get_results( "SELECT zones.zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones as zones
               LEFT OUTER JOIN {$wpdb->prefix}woocommerce_shipping_zone_locations as locations ON zones.zone_id = locations.zone_id AND location_type != 'postcode'
               WHERE " . implode( ' ', $criteria ) . "
               ORDER BY zone_order ASC", ARRAY_A );
}
