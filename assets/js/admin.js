/* 
 * Admin script.
 */

jQuery(document).ready(function ($) {

    if( $('label[for="woocommerce_shipping_individually_zone"]').length > 0 ){
            $('label[for="woocommerce_shipping_individually_zone"]').closest('td').css({"paddingTop":"0"});
    }
 
});