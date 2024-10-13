<?php

class Funky_Bidding_Shortcodes {
    // Display all campaigns
    // Enqueue styles for modern UI
    public function __construct() {
        add_shortcode('funky_bidding_campaigns', array($this, 'display_campaigns'));
        add_shortcode('funky_bidding_items', array($this, 'display_items'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('init', array($this, 'create_bidding_users_table'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data'));
    }

    public function enqueue_styles() {
        wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-styles.css');
    }

    // Display all campaigns with modern UI
    public function display_campaigns($atts) {
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bidding_campaigns");

        if (empty($campaigns)) {
            return '<p class="funky-bidding-message">No campaigns are currently running.</p>';
        }

        ob_start();
        echo '<div class="funky-bidding-campaigns">';
        foreach ($campaigns as $campaign) {
            echo '<div class="funky-bidding-campaign">';
            echo '<h2 class="funky-bidding-campaign-title">' . esc_html($campaign->name) . '</h2>';
            echo '<p class="funky-bidding-campaign-description">' . esc_html($campaign->description) . '</p>';
            if ($campaign->sponsorship_image) {
                echo '<img class="funky-bidding-campaign-image" src="' . esc_url($campaign->sponsorship_image) . '" alt="Sponsorship Image">';
            }
            echo '<a href="' . esc_url(get_permalink() . '?campaign_id=' . $campaign->id) . '" class="funky-bidding-button">View Items</a>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    // Display items for a campaign
    public function display_items($atts) {
        global $wpdb;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_items WHERE campaign_id = %d", $campaign_id));

        if (empty($items)) {
            return '<p>No items are available for bidding.</p>';
        }

        ob_start();
        echo '<div class="bidding-items">';
        foreach ($items as $item) {
            echo '<div class="item">';
            echo '<h3>' . esc_html($item->item_name) . '</h3>';
            if ($item->item_image) {
                echo '<img src="' . esc_url($item->item_image) . '" alt="Item Image">';
            }
            echo '<p>Minimum Bid: $' . esc_html($item->min_bid) . '</p>';
            echo '<p>Bid Increment: $' . esc_html($item->bid_increment) . '</p>';
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
            echo '<label for="user_email">Email:</label><br>';
            echo '<input type="email" name="user_email" required><br><br>';
            echo '<label for="bid_amount">Your Bid:</label><br>';
            echo '<input type="number" name="bid_amount" step="0.01" min="' . esc_attr($item->min_bid + $item->bid_increment) . '" required><br><br>';
            echo '<input type="submit" value="Place Bid" class="button button-primary">';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    // Display bidding items and handle bid placement
    public function display_bidding_items($atts) {
        global $wpdb;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_items WHERE campaign_id = %d", $campaign_id));

        if (empty($items)) {
            return '<p>No items are available for bidding.</p>';
        }

        ob_start();
        echo '<div class="bidding-items">';
        foreach ($items as $item) {
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));
            $min_bid = $highest_bid ? $highest_bid + $item->bid_increment : $item->min_bid;

            echo '<div class="item">';
            echo '<h3>' . esc_html($item->item_name) . '</h3>';
            if ($item->item_image) {
                echo '<img src="' . esc_url($item->item_image) . '" alt="Item Image">';
            }
            echo '<p>Current Highest Bid: $' . number_format($highest_bid ? $highest_bid : $item->min_bid, 2) . '</p>';
            echo '<p>Minimum Bid: $' . number_format($min_bid, 2) . '</p>';
            echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="place_bid">';
            echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
            echo '<label for="user_email">Email:</label><br>';
            echo '<input type="email" name="user_email" required><br><br>';
            echo '<label for="user_phone">Phone:</label><br>';
            echo '<input type="tel" name="user_phone" required><br><br>';
            echo '<label for="bid_amount">Your Bid:</label><br>';
            echo '<input type="number" name="bid_amount" step="0.01" min="' . esc_attr($min_bid) . '" required><br><br>';
            echo '<input type="submit" value="Place Bid" class="button button-primary">';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    // Handle bid placement
    public function handle_place_bid() {
        if (isset($_POST['item_id'], $_POST['user_email'], $_POST['user_phone'], $_POST['bid_amount'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_email = sanitize_email($_POST['user_email']);
            $user_phone = sanitize_text_field($_POST['user_phone']);
            $bid_amount = floatval($_POST['bid_amount']);

            // Validate bid
            $item = $wpdb->get_row($wpdb->prepare("SELECT min_bid, bid_increment FROM {$wpdb->prefix}bidding_items WHERE id = %d", $item_id));
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item_id));
            $min_next_bid = ($highest_bid) ? $highest_bid + $item->bid_increment : $item->min_bid;

            if ($bid_amount >= $min_next_bid) {
                // Insert or update user information
                $wpdb->replace(
                    "{$wpdb->prefix}bidding_users",
                    array(
                        'user_email' => $user_email,
                        'user_phone' => $user_phone
                    ),
                    array('%s', '%s')
                );

                // Place bid
                $wpdb->insert(
                    "{$wpdb->prefix}bidding_bids",
                    array(
                        'item_id' => $item_id,
                        'user_email' => $user_email,
                        'bid_amount' => $bid_amount,
                        'bid_time' => current_time('mysql')
                    )
                );

                wp_redirect(wp_get_referer() . '&bid_success=1');
                exit;
            } else {
                wp_redirect(wp_get_referer() . '&bid_error=1');
                exit;
            }
        }
    }

    // Create bidding_users table if it doesn't exist
    private function create_bidding_users_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bidding_users';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                user_email VARCHAR(100) NOT NULL PRIMARY KEY,
                user_phone VARCHAR(20) NOT NULL
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    // Enqueue scripts for AJAX and timer functionality
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('funky-bidding-scripts', plugin_dir_url(__FILE__) . '../assets/js/funky-bidding-scripts.js', array('jquery'), '1.0', true);
        wp_localize_script('funky-bidding-scripts', 'funkyBidding', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    // Display campaigns with AJAX refresh and timer
    public function display_campaigns($atts) {
        global $wpdb;
        $campaigns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bidding_campaigns");

        if (empty($campaigns)) {
            return '<p class="funky-bidding-message">No campaigns are currently running.</p>';
        }

        ob_start();
        echo '<div class="funky-bidding-campaigns">';
        foreach ($campaigns as $campaign) {
            $end_time = strtotime($campaign->end_date);
            $is_active = time() < $end_time;
            $class = $is_active ? 'active' : 'inactive';
            
            echo '<div class="funky-bidding-campaign ' . $class . '" data-campaign-id="' . esc_attr($campaign->id) . '">';
            echo '<h2 class="funky-bidding-campaign-title">' . esc_html($campaign->name) . '</h2>';
            echo '<p class="funky-bidding-campaign-description">' . esc_html($campaign->description) . '</p>';
            
            if ($campaign->sponsorship_image) {
                echo '<img class="funky-bidding-campaign-image" src="' . esc_url($campaign->sponsorship_image) . '" alt="Sponsorship Image">';
            }
            
            if ($is_active) {
                echo '<div class="funky-bidding-timer" data-end-time="' . esc_attr($end_time) . '"></div>';
                echo '<a href="' . esc_url(get_permalink() . '?campaign_id=' . $campaign->id) . '" class="funky-bidding-button">View Items</a>';
            } else {
                echo '<div class="funky-bidding-cancelled"><span class="dashicons dashicons-no-alt"></span> Campaign Ended</div>';
            }
            
            echo '</div>';
        }
        echo '</div>';

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        return ob_get_clean();
    }

    // AJAX handler to refresh campaign data
    public function refresh_campaign_data() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');
        
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        
        global $wpdb;
        $campaign = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_campaigns WHERE id = %d", $campaign_id));
        
        if ($campaign) {
            $end_time = strtotime($campaign->end_date);
            $is_active = time() < $end_time;
            
            $response = array(
                'is_active' => $is_active,
                'end_time' => $end_time
            );
            
            wp_send_json_success($response);
        } else {
            wp_send_json_error('Campaign not found');
        }
    }
}
