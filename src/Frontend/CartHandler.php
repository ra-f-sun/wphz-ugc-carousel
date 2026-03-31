<?php
namespace WPHZ\UGC\Frontend;
defined('ABSPATH') || exit;

use WPHZ\UGC\AbstractSingleton;

class CartHandler extends AbstractSingleton {

    public function init(): void {
        add_action('wp_ajax_wphz_ugc_add_to_cart',        [$this, 'handle']);
        add_action('wp_ajax_nopriv_wphz_ugc_add_to_cart', [$this, 'handle']);
    }

    public function handle(): void {
        // C6 Contract: Nonce verification. Dies with 403 if invalid.
        \WPHZ\UGC\Helpers\NonceHelper::verify('wphz_ugc_atc');

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $quantity   = (int) ($_POST['quantity']   ?? 1);

        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product.']);
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable()) {
            wp_send_json_error(['message' => 'Product not purchasable.']);
        }

        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            WC()->cart->calculate_totals();

            wp_send_json_success([
                'message'   => __('Added to cart.', 'wphz-ugc'),
                'fragments' => apply_filters('woocommerce_add_to_cart_fragments', []),
                'cart_hash' => WC()->cart->get_cart_hash(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Could not add to cart.']);
        }
    }
}
