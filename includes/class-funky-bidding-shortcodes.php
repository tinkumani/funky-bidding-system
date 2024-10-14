<?php

class Funky_Bidding_Shortcodes {
    public function __construct() {
        add_shortcode('funky_bidding_campaigns', array($this, 'display_campaigns'));
        add_shortcode('funky_bidding_items', array($this, 'display_items'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'create_bidding_users_table'));
        add_action('init', array($this, 'create_bidding_watchers_table'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('admin_post_place_bid', array($this, 'handle_place_bid'));
        add_action('admin_post_nopriv_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_nopriv_watch_item', array($this, 'handle_watch_item'));
    }

    public function enqueue_styles() {
        wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-styles.css');
    }

    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('funky-bidding-scripts', plugin_dir_url(__FILE__) . '../assets/js/funky-bidding-scripts.js', array('jquery'), '1.0', true);
        wp_localize_script('funky-bidding-scripts', 'funkyBidding', array(
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    public function create_bidding_users_table() {
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

    public function create_bidding_watchers_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bidding_watchers';
        
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                item_id mediumint(9) NOT NULL,
                user_email varchar(100) NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

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

        return ob_get_clean();
    }

    public function display_items($atts) {
        global $wpdb;
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        $items = $wpdb->get_results($wpdb->prepare("SELECT i.*, c.end_date FROM {$wpdb->prefix}bidding_items i JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id WHERE i.campaign_id = %d", $campaign_id));
    
        if (empty($items)) {
            return '<p>No items are available for bidding.</p>';
        }
    
        ob_start();
        echo '<div class="bidding-items-container">';
        echo '<div class="bidding-items">';
        
        foreach ($items as $item) {
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));
            $highest_bid = $highest_bid ? $highest_bid : $item->min_bid;
    
            $bid_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));
    
            $end_time = strtotime($item->end_date);
            $current_time = current_time('timestamp');
            $time_left = $end_time - $current_time;
    
            $is_sold = ($highest_bid >= $item->max_price && $item->max_price != 0) || ($time_left <= 0);
    
            echo '<div class="item' . ($is_sold ? ' sold' : '') . '">';
            if ($is_sold) {
                echo '<div class="sold-banner">SOLD</div>';
            }
            echo '<h3>' . esc_html($item->item_name) . '</h3>';
            if ($item->item_image) {
                echo '<div class="item-image-container">';
                echo '<img src="' . esc_url($item->item_image) . '" alt="Item Image">';
                echo '<button class="watch-item" data-item-id="' . esc_attr($item->id) . '">Watch Item</button>';
                echo '</div>';
            }
            echo '<p>Current Highest Bid: $<span class="highest-bid">' . number_format($highest_bid, 2) . '</span></p>';
            echo '<p>Minimum Bid: $' . esc_html($item->min_bid) . '</p>';
            echo '<p>Max Price: $' . esc_html($item->max_bid) . '</p>';
            echo '<p>Bid Increment: $' . esc_html($item->bid_increment) . '</p>';
            echo '<p>Total Bids: ' . $bid_count . '</p>';
            
            if (!$is_sold) {
                echo '<p>Time Left: <span class="timer" data-end-time="' . esc_attr($end_time) . '"></span></p>';
            } else {
                echo '<p>Sold for: $' . number_format($highest_bid, 2) . '</p>';
            }
    
            $bid_history = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d ORDER BY bid_time DESC LIMIT 5", $item->id));
            if (!empty($bid_history)) {
                echo '<h4>Recent Bids</h4>';
                echo '<ul class="bid-history">';
                foreach ($bid_history as $bid) {
                    echo '<li>$' . number_format($bid->bid_amount, 2) . ' - ' . date('Y-m-d H:i:s', strtotime($bid->bid_time)) . '</li>';
                }
                echo '</ul>';
            }
    
            if (!$is_sold) {
                echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="place_bid">';
                echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
                echo '<label for="user_email">Email:</label><br>';
                echo '<input type="email" name="user_email" required><br><br>';
                echo '<label for="bid_amount">Your Bid:</label><br>';
                echo '<input type="number" name="bid_amount" step="0.01" min="' . esc_attr($highest_bid + $item->bid_increment) . '" required><br><br>';
                echo '<input type="submit" value="Place Bid" class="button button-primary">';
                echo '</form>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    
        echo '<script>
        jQuery(document).ready(function($) {
            function updateTimer() {
                $(".timer").each(function() {
                    var endTime = $(this).data("end-time");
                    var now = Math.floor(Date.now() / 1000);
                    var timeLeft = endTime - now;
    
                    if (timeLeft > 0) {
                        var days = Math.floor(timeLeft / 86400);
                        var hours = Math.floor((timeLeft % 86400) / 3600);
                        var minutes = Math.floor((timeLeft % 3600) / 60);
                        var seconds = timeLeft % 60;
    
                        $(this).text(days + "d " + hours + "h " + minutes + "m " + seconds + "s");
                    } else {
                        $(this).text("Bidding ended");
                        $(this).closest(".item").addClass("sold");
                        $(this).closest(".item").find("form").remove();
                        $(this).closest(".item").prepend("<div class=\'sold-banner\'>SOLD</div>");
                    }
                });
            }
    
            setInterval(updateTimer, 1000);
            updateTimer();
    
            $(".bidding-items-container").css({
                "overflow-x": "scroll",
                "white-space": "nowrap"
            });
            $(".bidding-items").css({
                "display": "inline-block",
                "white-space": "nowrap"
            });
            $(".item").css({
                "display": "inline-block",
                "vertical-align": "top",
                "width": "300px",
                "margin-right": "20px"
            });
    
            $(".watch-item").click(function(e) {
                e.preventDefault();
                var itemId = $(this).data("item-id");
                var userEmail = prompt("Enter your email to watch this item:");
                if (userEmail) {
                    $.ajax({
                        url: funkyBidding.ajaxurl,
                        type: "POST",
                        data: {
                            action: "watch_item",
                            item_id: itemId,
                            user_email: userEmail
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("You are now watching this item!");
                            } else {
                                alert("Error: " + response.data);
                            }
                        }
                    });
                }
            });
        });
        </script>';
    
        return ob_get_clean();
    }

    public function handle_place_bid() {
        if (isset($_POST['item_id'], $_POST['user_email'], $_POST['bid_amount'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_email = sanitize_email($_POST['user_email']);
            $bid_amount = floatval($_POST['bid_amount']);

            $item = $wpdb->get_row($wpdb->prepare("SELECT min_bid, bid_increment, max_price FROM {$wpdb->prefix}bidding_items WHERE id = %d", $item_id));
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item_id));
            $min_next_bid = ($highest_bid) ? $highest_bid + $item->bid_increment : $item->min_bid;

            if ($bid_amount >= $min_next_bid && ($item->max_price == 0 || $bid_amount <= $item->max_price)) {
                $wpdb->insert(
                    "{$wpdb->prefix}bidding_bids",
                    array(
                        'item_id' => $item_id,
                        'user_email' => $user_email,
                        'bid_amount' => $bid_amount,
                        'bid_time' => current_time('mysql')
                    )
                );

                if ($wpdb->insert_id) {
                    $watchers = $wpdb->get_results($wpdb->prepare("SELECT user_email FROM {$wpdb->prefix}bidding_watchers WHERE item_id = %d", $item_id));
                    foreach ($watchers as $watcher) {
                        $subject = "New bid placed on watched item";
                        $message = "A new bid of $" . number_format($bid_amount, 2) . " has been placed on the item you're watching.";
                        wp_mail($watcher->user_email, $subject, $message);
                    }

                    wp_redirect(wp_get_referer() . '&bid_success=1');
                    exit;
                }
            }
        }
        wp_redirect(wp_get_referer() . '&bid_error=1');
        exit;
    }
    public function load_more_items() {
        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $items_per_page = 10;
    
        global $wpdb;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, c.end_date FROM {$wpdb->prefix}bidding_items i 
            JOIN {$wpdb->prefix}bidding_campaigns c ON i.campaign_id = c.id 
            WHERE i.campaign_id = %d 
            LIMIT %d OFFSET %d",
            $campaign_id, $items_per_page, ($page - 1) * $items_per_page
        ));
    
        $html_items = array();
        foreach ($items as $item) {
            ob_start();
            // Use your existing item HTML structure here
            echo '<div class="item">';
            // ... (your existing item HTML)
            echo '</div>';
            $html_items[] = ob_get_clean();
        }
    
        wp_send_json_success(['items' => $html_items]);
    }
    public function handle_watch_item() {
        if (isset($_POST['item_id']) && isset($_POST['user_email'])) {
            global $wpdb;
            $item_id = intval($_POST['item_id']);
            $user_email = sanitize_email($_POST['user_email']);

            $result = $wpdb->insert(
                "{$wpdb->prefix}bidding_watchers",
                array(
                    'item_id' => $item_id,
                    'user_email' => $user_email
                ),
                array('%d', '%s')
            );
    
            if ($result) {
                wp_send_json_success('You are now watching this item.');
            } else {
                wp_send_json_error('Failed to add watcher.');
            }
        } else {
            wp_send_json_error('Invalid request.');
        }
    }
    
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
    
    // Add this to your main plugin file or a separate file that's included in your plugin
    function funky_bidding_enqueue_styles() {
        wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . 'assets/css/funky-bidding-styles.css');
    }
    add_action('wp_enqueue_scripts', 'funky_bidding_enqueue_styles');
    
    // CSS for the bidding system
    function funky_bidding_inline_styles() {
        echo '<style>
        .bidding-items-container {
            height: 600px;
            overflow-y: auto;
        }
        .bidding-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .item {
            flex: 0 1 300px;
            display: flex;
                flex-direction: column;
                border: 1px solid #ddd;
                padding: 10px;
                position: relative;
            }
            .item-image-container {
                position: relative;
                margin-bottom: 10px;
            }
            .item-image-container img {
                max-width: 100%;
                height: auto;
            }
            .watch-item {
                position: absolute;
                bottom: 10px;
                right: 10px;
                background-color: rgba(0, 0, 0, 0.7);
                color: white;
                border: none;
                padding: 5px 10px;
                cursor: pointer;
            }
            .watch-item:hover {
                background-color: rgba(0, 0, 0, 0.9);
            }
            .item.sold {
                opacity: 0.7;
            }
            .sold-banner {
                background-color: red;
                color: white;
                padding: 5px 10px;
                position: absolute;
                top: 10px;
                right: 10px;
                transform: rotate(45deg);
            }
        </style>';
    }
    add_action('wp_head', 'funky_bidding_inline_styles');