<?php

class Funky_Bidding_Bids {

    public function __construct() {
        add_action('admin_post_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_place_bid', array($this, 'handle_place_bid'));
    }

    public function handle_place_bid() {
        if (isset($_POST['item_id'], $_POST['user_email'], $_POST['bid_amount'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_email = sanitize_email($_POST['user_email']);
            $bid_amount = floatval($_POST['bid_amount']);

            // Validate bid
            $item = $wpdb->get_row($wpdb->prepare("SELECT min_bid, bid_increment FROM {$wpdb->prefix}bidding_items WHERE id = %d", $item_id));
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item_id));
            $min_next_bid = ($highest_bid) ? $highest_bid + $item->bid_increment : $item->min_bid;

            if ($bid_amount >= $min_next_bid) {
                // Place bid
                $wpdb->insert(
                    "{$wpdb->prefix}bidding_bids",
                    array(
                        'item_id' => $item_id,
                        'user_email' => $user_email,
                        'bid_amount' => $bid_amount
                    )
                );

                // Redirect back to item page
                wp_redirect(wp_get_referer() . '&bid_success=1');
                exit;
            } else {
                // Redirect back with error
                wp_redirect(wp_get_referer() . '&bid_error=1');
                exit;
            }
        }
    }
}
