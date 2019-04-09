=== WooCommerce Individually Calculated Shipping Zones ===
Author: Anton Shulga
Tags: woocommerce, shipping
Requires at least: 4.9.4
Tested up to: 4.9.4
Requires WooCommerce at least: 3.3.3
Tested WooCommerce up to: 3.3.3

To choose a Shipping Zone on Cart/Checkout-page

== Installation ==

1. Upload the entire 'woocommerce-individually-calculated-shipping-zones' folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Set up your shipping zones
4. Check in the Woo Shipping option "Charge shipping for each shipping zone individually"
5. Add function tsc_cart_totals_shipping_html() on your theme on /themes/YOUR-THEME/woocommerce/cart/cart-totals.php. For example:

<?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>

<?php
if( get_option( 'woocommerce_shipping_individually_zone' ) == 'yes' ){                           
        tsc_cart_totals_shipping_html();
}else{
        wc_cart_totals_shipping_html();
}
?>

<?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>
