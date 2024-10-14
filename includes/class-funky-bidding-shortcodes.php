<?php


class Funky_Bidding_Shortcodes {
    public function __construct() {
        add_shortcode('funky_bidding_campaigns', array($this, 'display_campaigns'));
        add_shortcode('funky_bidding_items', array($this, 'display_items'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('init', array($this, 'create_bidding_users_table'));
        add_action('init', array($this, 'create_bidding_watchers_table'));
        add_action('wp_ajax_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('wp_ajax_nopriv_refresh_campaign_data', array($this, 'refresh_campaign_data'));
        add_action('admin_post_place_bid', array($this, 'handle_place_bid'));
        add_action('admin_post_nopriv_place_bid', array($this, 'handle_place_bid'));
        add_action('wp_ajax_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_nopriv_watch_item', array($this, 'handle_watch_item'));
        add_action('wp_ajax_load_more_items', array($this, 'load_more_items'));
        add_action('wp_ajax_nopriv_load_more_items', array($this, 'load_more_items'));
    }

    public function enqueue_assets() {
        wp_enqueue_style('funky-bidding-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-styles.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('funky-bidding-scripts', plugin_dir_url(__FILE__) . '../assets/js/funky-bidding-scripts.js', array('jquery'), '1.0', true);
        wp_localize_script('funky-bidding-scripts', 'funkyBidding', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('funky_bidding_nonce')
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

        // Enqueue the new stylesheet
        wp_enqueue_style('funky-bidding-campaign-styles', plugin_dir_url(__FILE__) . '../assets/css/funky-bidding-campaign-styles.css');

        ob_start();
        echo '<div class="funky-bidding-campaigns">';
        foreach ($campaigns as $campaign) {
            $end_time = strtotime($campaign->end_date);
            $is_active = time() < $end_time;
            $class = $is_active ? 'active' : 'inactive';
            
            // Fetch campaign statistics
            $total_money = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(b.bid_amount) 
                FROM {$wpdb->prefix}bidding_bids b
                JOIN {$wpdb->prefix}bidding_items i ON b.item_id = i.id
                WHERE i.campaign_id = %d",
                $campaign->id
            ));
            $items_sold = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT b.item_id) 
                FROM {$wpdb->prefix}bidding_bids b
                JOIN {$wpdb->prefix}bidding_items i ON b.item_id = i.id
                WHERE i.campaign_id = %d",
                $campaign->id
            ));
            $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}bidding_items WHERE campaign_id = %d",
                $campaign->id
            ));
            $items_to_be_sold = $total_items - $items_sold;
            
            echo '<div class="funky-bidding-campaign ' . $class . '" data-campaign-id="' . esc_attr($campaign->id) . '">';
            echo '<div class="funky-bidding-campaign-header">';
            echo '<h2 class="funky-bidding-campaign-title">' . esc_html($campaign->name) . '</h2>';
            if ($is_active) {
                echo '<div class="funky-bidding-timer" data-end-time="' . esc_attr($end_time) . '"></div>';
            } else {
                echo '<div class="funky-bidding-cancelled"><span class="dashicons dashicons-no-alt"></span> Campaign Ended</div>';
            }
            echo '</div>';
            
            echo '<div class="funky-bidding-campaign-content">';
            echo '<div class="funky-bidding-campaign-image-container">';
            if ($campaign->sponsorship_image) {
                echo '<img class="funky-bidding-campaign-image" src="' . esc_url($campaign->sponsorship_image) . '" alt="Sponsorship Image">';
            }
            echo '</div>';
            
            echo '<div class="funky-bidding-campaign-details">';
            echo '<p class="funky-bidding-campaign-description">' . esc_html($campaign->description) . '</p>';
            
            echo '<div class="funky-bidding-campaign-stats">';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Total Raised:</span> <span class="funky-bidding-stat-value">$' . number_format($total_money, 2) . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Items Sold:</span> <span class="funky-bidding-stat-value">' . $items_sold . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Items Remaining:</span> <span class="funky-bidding-stat-value">' . $items_to_be_sold . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">Start Date:</span> <span class="funky-bidding-stat-value">' . esc_html($campaign->start_date) . '</span></div>';
            echo '<div class="funky-bidding-stat"><span class="funky-bidding-stat-label">End Date:</span> <span class="funky-bidding-stat-value">' . esc_html($campaign->end_date) . '</span></div>';
            echo '</div>';
            
            if ($is_active) {
                echo '<a href="' . esc_url(get_permalink() . '?campaign_id=' . $campaign->id) . '" class="funky-bidding-button">View Items</a>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    public function display_items($atts) {
        $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
        
        ob_start();
        echo '<div id="funky-bidding-items-container" class="funky-bidding-items-container" data-campaign-id="' . esc_attr($campaign_id) . '">';
        echo '<div id="funky-bidding-items" class="funky-bidding-items"></div>';
        echo '<div id="funky-bidding-loader" class="funky-bidding-loader">Loading...</div>';
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            var page = 1;
            var loading = false;
            var $container = $("#funky-bidding-items-container");
            var $itemsContainer = $("#funky-bidding-items");
            var $loader = $("#funky-bidding-loader");

            function loadItems() {
                if (loading) return;
                loading = true;
                $loader.show();

                $.ajax({
                    url: funkyBidding.ajaxurl,
                    type: "POST",
                    data: {
                        action: "load_more_items",
                        campaign_id: $container.data("campaign-id"),
                        page: page,
                        nonce: funkyBidding.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.items.length > 0) {
                            $itemsContainer.append(response.data.items.join(""));
                            page++;
                            initializeNewItems();
                        } else {
                            $loader.text("No more items to load.");
                        }
                        loading = false;
                        $loader.hide();
                    },
                    error: function() {
                        loading = false;
                        $loader.hide();
                        $loader.text("Error loading items. Please try again.");
                    }
                });
            }

            $container.on("scroll", function() {
                if ($container.scrollTop() + $container.innerHeight() >= $itemsContainer.height() - 200) {
                    loadItems();
                }
            });

            loadItems();

            function initializeNewItems() {
                $(".watch-item").off("click").on("click", function(e) {
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
                                user_email: userEmail,
                                nonce: funkyBidding.nonce
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

                updateTimers();
            }

            function updateTimers() {
                $(".timer").each(function() {
                    var $timer = $(this);
                    var endTime = $timer.data("end-time");
                    var now = Math.floor(Date.now() / 1000);
                    var timeLeft = endTime - now;

                    if (timeLeft > 0) {
                        var days = Math.floor(timeLeft / 86400);
                        var hours = Math.floor((timeLeft % 86400) / 3600);
                        var minutes = Math.floor((timeLeft % 3600) / 60);
                        var seconds = timeLeft % 60;

                        $timer.text(days + "d " + hours + "h " + minutes + "m " + seconds + "s");
                    } else {
                        $timer.text("Bidding ended");
                        var $item = $timer.closest(".funky-bidding-item");
                        $item.addClass("sold");
                        $item.find("form").remove();
                        $item.prepend("<div class=\'sold-banner\'>SOLD</div>");
                    }
                });
            }

            setInterval(updateTimers, 1000);
        });
        </script>';

        return ob_get_clean();
    }

    public function load_more_items() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');

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
            $highest_bid = $wpdb->get_var($wpdb->prepare("SELECT MAX(bid_amount) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));
            $highest_bid = $highest_bid ? $highest_bid : $item->min_bid;

            $bid_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}bidding_bids WHERE item_id = %d", $item->id));

            $end_time = strtotime($item->end_date);
            $current_time = current_time('timestamp');
            $time_left = $end_time - $current_time;

            $is_sold = ($highest_bid >= $item->max_bid && $item->max_bid != 0) || ($time_left <= 0);

            ob_start();
            echo '<div class="funky-bidding-item' . ($is_sold ? ' sold' : '') . '">';
            if ($is_sold) {
                echo '<div class="sold-banner">SOLD</div>';
            }
            echo '<h3>' . esc_html($item->item_name) . '</h3>';
            if ($item->item_image) {
                echo '<div class="item-image-container">';
                echo '<img src="' . esc_url($item->item_image) . '" alt="Item Image">';
                if (!$is_sold) {
                    echo '<button class="watch-item" data-item-id="' . esc_attr($item->id) . '">Watch Item</button>';
                }
                echo '</div>';
            }
            echo '<p>Current Highest Bid: $<span class="highest-bid">' . number_format($highest_bid, 2) . '</span></p>';
            echo '<p>Minimum Bid: $' . esc_html($item->min_bid) . '</p>';
            echo '<p>Max Price: $' . esc_html($item->max_bid) . '</p>';
            echo '<p>Bid Increment: $' . esc_html($item->bid_increment) . '</p>';
            echo '<p>Total Bids: ' . $bid_count . '</p>';
            
            if (!$is_sold) {
                echo '<p>Time Left: <span class="timer" data-end-time="' . esc_attr($end_time) . '"></span></p>';
                echo '<form method="POST" action="' . esc_url(admin_url('admin-post.php')) . '">';
                echo '<input type="hidden" name="action" value="place_bid">';
                echo '<input type="hidden" name="item_id" value="' . esc_attr($item->id) . '">';
                echo '<label for="user_email">Email:</label>';
                echo '<input type="email" name="user_email" required>';
                echo '<label for="bid_amount">Your Bid:</label>';
                echo '<input type="number" name="bid_amount" step="0.01" min="' . esc_attr($highest_bid + $item->bid_increment) . '" required>';
                echo '<input type="submit" value="Place Bid" class="funky-bidding-button">';
                echo '</form>';
            } else {
                echo '<p>Sold for: $' . number_format($highest_bid, 2) . '</p>';
            }
            echo '</div>';
            $html_items[] = ob_get_clean();
        }

        wp_send_json_success(['items' => $html_items]);
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

    public function handle_watch_item() {
        check_ajax_referer('funky_bidding_nonce', 'nonce');

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
    .funky-bidding-container {
        display: flex;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .funky-bidding-sidebar {
        width: 25%;
        padding-right: 20px;
    }
    .funky-bidding-main-content {
        width: 75%;
    }
    .funky-bidding-campaign-list {
        list-style-type: none;
        padding: 0;
    }
    .funky-bidding-campaign {
        margin-bottom: 20px;
        padding: 10px;
        background-color: #f5f5f5;
        border-radius: 5px;
    }
    .funky-bidding-items-container {
        height: 800px;
        overflow-y: auto;
    }
    .funky-bidding-items {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    .funky-bidding-item {
        background-color: #ffffff;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease-in-out;
        position: relative; /* Add this line */
    }
    .funky-bidding-item:hover {
        transform: translateY(-5px);
    }
    .funky-bidding-item h3 {
        margin-top: 0;
        font-size: 16px;
        color: #333;
    }
    .item-image-container {
        position: relative;
        margin-bottom: 10px;
    }
    .item-image-container img {
        width: 100%;
        height: auto;
        border-radius: 6px;
        object-fit: cover;
        aspect-ratio: 16 / 9;
    }
    .watch-item {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        padding: 5px 8px;
        font-size: 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }
    .watch-item:hover {
        background-color: rgba(0, 0, 0, 0.9);
    }
    .funky-bidding-item.sold {
        opacity: 0.7;
    }
    .sold-banner {
        background-color: #ff4136;
        color: white;
        padding: 5px 10px;
        position: absolute;
        top: 10px;
        left: 10px;
        font-size: 14px;
        font-weight: bold;
        z-index: 1;
        transform: rotate(-15deg);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .funky-bidding-button {
        background-color: #0074D9;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }
    .funky-bidding-button:hover {
        background-color: #0063b1;
    }
    .funky-bidding-loader {
        text-align: center;
        padding: 20px;
        font-style: italic;
        color: #666;
    }
    @media (max-width: 768px) {
        .funky-bidding-container {
            flex-direction: column;
            padding: 10px;
        }
        .funky-bidding-sidebar,
        .funky-bidding-main-content {
            width: 100%;
        }
        .funky-bidding-items-container {
            height: auto;
            max-height: none;
        }
        .funky-bidding-items {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .funky-bidding-item {
            padding: 8px;
        }
        .funky-bidding-item h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .item-image-container {
            margin-bottom: 5px;
        }
        .item-image-container img {
            aspect-ratio: 1 / 1;
        }
        .watch-item {
            font-size: 10px;
            padding: 2px 4px;
        }
        .funky-bidding-item p {
            font-size: 12px;
            margin: 3px 0;
        }
        .funky-bidding-button {
            padding: 5px 8px;
            font-size: 12px;
        }
        .bid-form input[type="email"],
        .bid-form input[type="number"] {
            width: 100%;
            margin-bottom: 5px;
        }
    }
    </style>';
}
add_action('wp_head', 'funky_bidding_inline_styles');